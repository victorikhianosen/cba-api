<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

// Idle re-authentication ("session lock"), distinct from a logout — the Sanctum token
// stays valid the whole time, we just gate further requests behind a TOTP re-check.
// Keyed by token id (not user id) so locking one device doesn't lock another session
// the same user has open elsewhere.
class SessionLockService
{
    // If a locked session sits unlocked this long, CheckSessionLockMiddleware revokes
    // the token outright and forces a real login — matches the spec's 30-60 min range.
    public const MAX_LOCK_MINUTES = 30;

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 10;

    public function __construct(
        private AuditService $audit,
        private Google2FA $google2fa,
    ) {}

    public function lock(User $user, int $tokenId): void
    {
        Cache::put(
            "session_lock:{$tokenId}",
            ['locked_at' => now()->toIso8601String()],
            now()->addMinutes(self::MAX_LOCK_MINUTES),
        );

        $this->audit->log(
            action: 'session_locked',
            module: 'auth',
            auditable: $user,
            description: "User '{$user->username}' ({$user->code}) locked their session.",
        );
    }

    public function isLocked(int $tokenId): bool
    {
        return Cache::has("session_lock:{$tokenId}");
    }

    public function unlock(User $user, int $tokenId, string $code): void
    {
        $attemptsKey = "session_unlock_attempts:{$tokenId}";

        if ((int) Cache::get($attemptsKey, 0) >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => ['The authentication code is invalid or has expired.'],
            ]);
        }

        if (! $user->google2fa_secret || ! $this->google2fa->verifyKey($user->google2fa_secret, $code)) {
            Cache::put($attemptsKey, (int) Cache::get($attemptsKey, 0) + 1, now()->addMinutes(self::LOCKOUT_MINUTES));

            throw ValidationException::withMessages([
                'code' => ['The authentication code is invalid or has expired.'],
            ]);
        }

        Cache::forget("session_lock:{$tokenId}");
        Cache::forget($attemptsKey);

        $this->audit->log(
            action: 'session_unlocked',
            module: 'auth',
            auditable: $user,
            description: "User '{$user->username}' ({$user->code}) unlocked their session.",
        );
    }
}
