<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\LoginRequest;
use App\Http\Requests\Admin\User\TwoFactorVerifyRequest;
use App\Http\Resources\Admin\User\UserResource;
use App\Services\Auth\SessionLockService;
use App\Services\Setting\SettingService;
use App\Services\User\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuthService $auth,
        private SessionLockService $sessionLock,
        private SettingService $settings,
    ) {}

    public function login(LoginRequest $request)
    {
        try {
            $result = $this->auth->login($request->validated());

            // security.authentication.google_auth_unlock disabled: login() skipped the 2FA
            // challenge entirely and returned a real access token, same shape verifyTwoFactor()
            // returns — hand it straight back instead of the two-factor-challenge payload.
            if (array_key_exists('token', $result)) {
                return $this->success(
                    message: 'Login successful.',
                    data: [
                        'user'         => new UserResource($result['user']),
                        'access_token' => $result['token'],
                    ],
                );
            }

            return $this->success(
                message: $result['requires_setup']
                    ? 'Scan the QR code with Google Authenticator to finish setting up your account.'
                    : 'Password verified. Enter the code from your Google Authenticator app to continue.',
                data: [
                    'requires_setup'    => $result['requires_setup'],
                    'qr_code'           => $result['qr_code'] ?? null,
                    'secret'            => $result['secret'] ?? null,
                    'two_factor_token'  => $result['two_factor_token'],
                ],
                responseCode: $result['requires_setup'] ? '201' : '000',
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage() ?: 'Invalid credentials provided.',
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    public function verifyTwoFactor(TwoFactorVerifyRequest $request)
    {
        try {
            $result = $this->auth->verifyTwoFactor(
                $request->validated()['two_factor_token'],
                $request->validated()['code'],
            );

            return $this->success(
                message: 'Login successful.',
                data: [
                    'user'         => new UserResource($result['user']),
                    'access_token' => $result['token'],
                ],
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage() ?: 'Invalid authentication code.',
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('user')->load(['roles', 'permissions']);

        return $this->success(
            message: 'User details retrieved successfully.',
            data: new UserResource($user),
        );
    }

    public function logout(Request $request)
    {
        try {
            $this->auth->logout($request->user('user'));

            return $this->success(
                message: 'Logged out successfully.',
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request. Please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    // Called by the frontend's own idle timer just before it clears the local session —
    // the token is still valid at this point, so the resulting journey/audit entries are
    // correctly attributed as a timeout rather than a manual logout.
    public function sessionTimeout(Request $request)
    {
        try {
            $this->auth->logout($request->user('user'), eventType: 'session_timeout');

            return $this->success(
                message: 'Session timed out.',
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request. Please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    // Any authenticated user (not gated behind view_settings) — just reads two settings,
    // not the full admin settings list. Also reports whether THIS token's session is
    // currently locked server-side, so a page reload (or a fresh navigation typed into the
    // address bar) can restore the lock overlay instead of losing it along with the rest of
    // the client's in-memory state. Reachable in the 'unlocked' route group even while locked.
    public function lockConfig(Request $request): JsonResponse
    {
        $user = $request->user('user');
        $tokenId = $user?->currentAccessToken()?->id;

        return $this->success(
            message: 'Session lock configuration retrieved successfully.',
            data: [
                'enabled'         => (bool) $this->settings->get('security.session.session_lock_enabled', true),
                'timeout_seconds' => (int) $this->settings->get('security.session.session_lock_timeout', 300),
                'locked'          => $tokenId ? $this->sessionLock->isLocked((int) $tokenId) : false,
            ],
        );
    }

    public function lockSession(Request $request): JsonResponse
    {
        try {
            $user = $request->user('user');
            $tokenId = (int) $user->currentAccessToken()->id;

            $this->sessionLock->lock($user, $tokenId);

            return $this->success(
                message: 'Session locked successfully.',
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request. Please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    public function unlockSession(Request $request): JsonResponse
    {
        try {
            $user = $request->user('user');
            $tokenId = (int) $user->currentAccessToken()->id;

            $this->sessionLock->unlock($user, $tokenId, $request->string('code')->value());

            return $this->success(
                message: 'Session unlocked successfully.',
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request. Please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }
}
