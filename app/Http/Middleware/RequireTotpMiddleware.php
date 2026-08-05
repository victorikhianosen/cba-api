<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FAQRCode\Google2FA;
use Symfony\Component\HttpFoundation\Response;

// Step-up verification for money-moving approvals: on top of the `permission:approve_*`
// check, the acting admin must also submit their current Google Authenticator code
// (`totp_code`) — the same google2fa_secret verified at login (see AuthService), just
// re-checked at the moment of approval so a hijacked/idle session alone isn't enough to
// move money. Attempt lockout mirrors AuthService's login 2FA challenge (5 attempts / 10
// minutes), keyed per-user rather than per-login-token since there's no challenge token here.
class RequireTotpMiddleware
{
    use ApiResponse;

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 10;

    public function __construct(private Google2FA $google2fa) {}

    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user');

        if (! $user) {
            return $this->error(
                message: 'Unauthenticated.',
                responseCode: '401',
                statusCode: 401,
            );
        }

        $attemptsKey = "totp_step_up_attempts:{$user->id}";

        if ((int) Cache::get($attemptsKey, 0) >= self::MAX_ATTEMPTS) {
            return $this->error(
                message: 'Too many incorrect authentication codes. Please wait a few minutes and try again.',
                responseCode: '429',
                statusCode: 429,
            );
        }

        $code = trim((string) $request->input('totp_code'));

        if ($code === '') {
            return $this->error(
                message: 'A Google Authenticator code is required to approve this.',
                responseCode: '101',
                statusCode: 422,
                errors: ['totp_code' => ['The totp_code field is required.']],
            );
        }

        if (! $user->google2fa_secret || ! $this->google2fa->verifyKey($user->google2fa_secret, $code)) {
            Cache::put($attemptsKey, (int) Cache::get($attemptsKey, 0) + 1, now()->addMinutes(self::LOCKOUT_MINUTES));

            return $this->error(
                message: 'The authentication code is invalid or has expired.',
                responseCode: '101',
                statusCode: 422,
                errors: ['totp_code' => ['The authentication code is invalid or has expired.']],
            );
        }

        Cache::forget($attemptsKey);

        return $next($request);
    }
}
