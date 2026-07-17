<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\LoginRequest;
use App\Http\Requests\Admin\User\TwoFactorVerifyRequest;
use App\Http\Resources\Admin\User\UserResource;
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
    ) {}

    public function login(LoginRequest $request)
    {
        try {
            $result = $this->auth->login($request->validated());

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
}
