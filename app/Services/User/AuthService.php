<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

class AuthService
{
    private const TWO_FACTOR_PURPOSE = 'two_factor_auth';

    private const TWO_FACTOR_TTL_MINUTES = 10;

    public function __construct(
        private AuditService $audit,
        private Google2FA $google2fa,
    ) {}

    /**
     * Validate credentials and hand back a two-factor challenge instead of
     * an access token. New users (no confirmed secret yet) get a QR code to
     * scan; returning users are just asked for the code from their app.
     */
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

    /**
     * Confirm the Google Authenticator code and issue the real access token.
     * On a user's first confirmation this also marks 2FA as enabled, so
     * later logins skip the QR-scan step and only ask for the code.
     */
    public function verifyTwoFactor(string $twoFactorToken, string $code): array
    {
        $user = $this->resolveTwoFactorUser($twoFactorToken);

        if (! $user->google2fa_secret || ! $this->google2fa->verifyKey($user->google2fa_secret, $code)) {
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

        $firstTimeSetup = ! $user->enable_2fa;

        if ($firstTimeSetup) {
            $user->forceFill(['enable_2fa' => true])->save();
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $this->audit->log(
            action: 'login',
            module: 'auth',
            auditable: $user,
            description: $firstTimeSetup
                ? "User '{$user->username}' ({$user->code}) completed Google Authenticator setup and logged in successfully."
                : "User '{$user->username}' ({$user->code}) logged in successfully.",
        );

        return [
            'user'  => $user->load(['roles', 'permissions']),
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();

        $this->audit->log(
            action: 'logout',
            module: 'auth',
            auditable: $user,
            description: "User '{$user->username}' ({$user->code}) logged out.",
        );
    }

    private function issueTwoFactorToken(User $user): string
    {
        return Crypt::encrypt([
            'user_id'    => $user->id,
            'purpose'    => self::TWO_FACTOR_PURPOSE,
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES)->getTimestamp(),
        ]);
    }

    private function resolveTwoFactorUser(string $twoFactorToken): User
    {
        try {
            $payload = Crypt::decrypt($twoFactorToken);
        } catch (DecryptException $e) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['This verification session is invalid. Please log in again.'],
            ]);
        }

        if (
            ! is_array($payload)
            || ($payload['purpose'] ?? null) !== self::TWO_FACTOR_PURPOSE
            || ($payload['expires_at'] ?? 0) < now()->getTimestamp()
        ) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['This verification session has expired. Please log in again.'],
            ]);
        }

        $user = User::find($payload['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['This verification session is invalid. Please log in again.'],
            ]);
        }

        return $user;
    }
}
