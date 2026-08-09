<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Audit\AuditService;
use App\Services\Auth\SessionLockService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

// Gates every authenticated admin request behind an idle-session lock (see
// SessionLockService) — NOT applied to the session lock/unlock endpoints themselves or
// to logout, so a locked user can still reach the unlock screen.
class CheckSessionLockMiddleware
{
    use ApiResponse;

    public function __construct(
        private SessionLockService $sessionLock,
        private AuditService $audit,
    ) {}

    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user');

        if (! $user) {
            return $next($request);
        }

        $tokenId = $user->currentAccessToken()?->id;

        if (! $tokenId) {
            return $next($request);
        }

        if (! $this->sessionLock->isLocked((int) $tokenId)) {
            return $next($request);
        }

        $payload = Cache::get("session_lock:{$tokenId}");
        $lockedAt = isset($payload['locked_at']) ? Carbon::parse($payload['locked_at']) : now();

        if ($lockedAt->diffInMinutes(now()) >= SessionLockService::MAX_LOCK_MINUTES) {
            $user->currentAccessToken()->delete();

            $this->audit->log(
                action: 'session_lock_expired_logout',
                module: 'auth',
                auditable: $user,
                description: "User '{$user->username}' ({$user->code}) was logged out after their locked session exceeded the maximum lock duration.",
            );

            return $this->error(
                message: 'Your session was locked for too long and has been logged out. Please sign in again.',
                responseCode: '401',
                statusCode: 401,
            );
        }

        return $this->error(
            message: 'Your session is locked. Please unlock to continue.',
            responseCode: '423',
            statusCode: 423,
        );
    }
}
