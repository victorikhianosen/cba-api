<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Journey\JourneyService;
use App\Services\Setting\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

class AuthService
{
    private const TWO_FACTOR_TTL_MINUTES = 10;

    // A token gets locked out after this many wrong codes, closing the window to brute-force
    // the 6-digit code against a single captured token before it naturally expires.
    private const TWO_FACTOR_MAX_ATTEMPTS = 5;

    public function __construct(
        private AuditService $audit,
        private Google2FA $google2fa,
        private JourneyService $journeys,
        private SettingService $settings,
    ) {}

    // Hands back a two-factor challenge instead of an access token — new users get a QR code to scan, returning users just get asked for the code.
    public function login(array $credentials): array
    {
        $user = User::where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->log(
                action: 'login_failed',
                module: 'auth',
                auditable: $user,
                description: "Failed login attempt for username '{$credentials['username']}'.",
            );

            throw ValidationException::withMessages([
                'username' => ['Invalid credentials provided.'],
            ]);
        }

        if ($user->status !== 'active') {
            $this->audit->log(
                action: 'login_blocked',
                module: 'auth',
                auditable: $user,
                description: "Login blocked for user '{$user->username}' ({$user->code}) — account status is '{$user->status}'.",
            );

            throw ValidationException::withMessages([
                'username' => ['This account is not active. Please contact your administrator.'],
            ]);
        }

        // security.authentication.google_auth_unlock off: skip the 2FA challenge entirely
        // and issue a real access token straight away, same shape verifyTwoFactor() returns
        // — so login()'s two response shapes are distinguished purely by the presence of
        // `token`, and every downstream consumer (frontend, this class's own callers)
        // already knows how to handle that shape.
        if (! $this->settings->get('security.authentication.google_auth_unlock', true)) {
            $newToken = $user->createToken('api-token');

            $this->audit->log(
                action: 'login',
                module: 'auth',
                auditable: $user,
                description: "User '{$user->username}' ({$user->code}) logged in successfully (Google Authenticator not required).",
            );

            $this->journeys->recordLogin($user, (string) $newToken->accessToken->getKey());

            return [
                'user'  => $user->load(['roles', 'permissions']),
                'token' => $newToken->plainTextToken,
            ];
        }

        $twoFactorToken = $this->issueTwoFactorToken($user);

        if (! $user->enable_2fa) {
            $secret = $this->google2fa->generateSecretKey();
            $user->forceFill(['google2fa_secret' => $secret])->save();

            $qrCode = $this->google2fa->getQRCodeInline(
                config('app.name'),
                $user->username,
                $secret,
            );

            $this->audit->log(
                action: 'two_factor_setup_initiated',
                module: 'auth',
                auditable: $user,
                description: "User '{$user->username}' ({$user->code}) was issued a new Google Authenticator setup QR code.",
            );

            return [
                'requires_setup'    => true,
                'qr_code'           => $qrCode,
                'secret'            => $secret,
                'two_factor_token'  => $twoFactorToken,
            ];
        }

        $this->audit->log(
            action: 'two_factor_challenge_issued',
            module: 'auth',
            auditable: $user,
            description: "User '{$user->username}' ({$user->code}) passed password check and was prompted for a Google Authenticator code.",
        );

        return [
            'requires_setup'   => false,
            'two_factor_token' => $twoFactorToken,
        ];
    }

    // Confirms the Google Authenticator code and issues the real access token; a first confirmation also marks 2FA enabled so later logins skip the QR step.
    public function verifyTwoFactor(string $twoFactorToken, string $code): array
    {
        $user = $this->resolveTwoFactorUser($twoFactorToken);

        if (! $user->google2fa_secret || ! $this->google2fa->verifyKey($user->google2fa_secret, $code)) {
            $this->registerFailedTwoFactorAttempt($twoFactorToken);

            $this->audit->log(
                action: 'two_factor_failed',
                module: 'auth',
                auditable: $user,
                description: "User '{$user->username}' ({$user->code}) submitted an invalid Google Authenticator code.",
            );

            throw ValidationException::withMessages([
                'code' => ['The authentication code is invalid or has expired.'],
            ]);
        }

        // One-time use: burn the challenge the moment it succeeds so the same token + code
        // pair can never be replayed to mint additional access tokens.
        Cache::forget($this->twoFactorCacheKey($twoFactorToken));
        Cache::forget($this->twoFactorAttemptsCacheKey($twoFactorToken));

        $firstTimeSetup = ! $user->enable_2fa;

        if ($firstTimeSetup) {
            $user->forceFill(['enable_2fa' => true])->save();
        }

        $newToken = $user->createToken('api-token');

        $this->audit->log(
            action: 'login',
            module: 'auth',
            auditable: $user,
            description: $firstTimeSetup
                ? "User '{$user->username}' ({$user->code}) completed Google Authenticator setup and logged in successfully."
                : "User '{$user->username}' ({$user->code}) logged in successfully.",
        );

        // The token's own id is the session identifier a journey timeline groups
        // on — one login (token issued) through one logout/timeout (token revoked).
        $this->journeys->recordLogin($user, (string) $newToken->accessToken->getKey());

        return [
            'user'  => $user->load(['roles', 'permissions']),
            'token' => $newToken->plainTextToken,
        ];
    }

    // $eventType distinguishes a manual logout from an idle-session timeout — the
    // frontend calls this same flow either way, just through a different endpoint,
    // since both revoke the current token identically.
    public function logout(User $user, string $eventType = 'logout'): void
    {
        $sessionId = (string) $user->currentAccessToken()?->getKey();

        $user->currentAccessToken()->delete();

        $this->audit->log(
            action: $eventType === 'session_timeout' ? 'session_timeout' : 'logout',
            module: 'auth',
            auditable: $user,
            description: $eventType === 'session_timeout'
                ? "User '{$user->username}' ({$user->code}) was logged out after the session timed out."
                : "User '{$user->username}' ({$user->code}) logged out.",
        );

        if ($sessionId !== '') {
            $this->journeys->recordLogout($user, $sessionId, $eventType);
        }
    }

    // Random opaque token backed by a cache entry (not a self-contained encrypted payload) so it
    // can actually be invalidated server-side the moment it's used — Crypt::encrypt alone can only
    // expire, never be revoked, which let a captured token+code pair be replayed indefinitely.
    private function issueTwoFactorToken(User $user): string
    {
        $token = Str::random(64);

        Cache::put($this->twoFactorCacheKey($token), $user->id, now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES));

        return $token;
    }

    private function resolveTwoFactorUser(string $twoFactorToken): User
    {
        if ($this->twoFactorAttempts($twoFactorToken) >= self::TWO_FACTOR_MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['Too many incorrect attempts. Please log in again.'],
            ]);
        }

        $userId = Cache::get($this->twoFactorCacheKey($twoFactorToken));

        if (! $userId) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['This verification session is invalid or has expired. Please log in again.'],
            ]);
        }

        $user = User::find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['This verification session is invalid. Please log in again.'],
            ]);
        }

        return $user;
    }

    private function registerFailedTwoFactorAttempt(string $twoFactorToken): void
    {
        $key = $this->twoFactorAttemptsCacheKey($twoFactorToken);

        Cache::put($key, $this->twoFactorAttempts($twoFactorToken) + 1, now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES));
    }

    private function twoFactorAttempts(string $twoFactorToken): int
    {
        return (int) Cache::get($this->twoFactorAttemptsCacheKey($twoFactorToken), 0);
    }

    private function twoFactorCacheKey(string $twoFactorToken): string
    {
        return "two_factor_token:{$twoFactorToken}";
    }

    private function twoFactorAttemptsCacheKey(string $twoFactorToken): string
    {
        return "two_factor_token_attempts:{$twoFactorToken}";
    }
}
