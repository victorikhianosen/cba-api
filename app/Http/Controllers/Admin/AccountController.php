<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Account\UpdateAccountStatusRequest;
use App\Http\Resources\Admin\Account\AccountResource;
use App\Services\Account\AccountService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AccountService $accounts,
    ) {}

    public function show(int $id): JsonResponse
    {
        try {
            $account = $this->accounts->find($id);

            return $this->success(
                message: 'Account retrieved successfully.',
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
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

    public function updateStatus(UpdateAccountStatusRequest $request, int $id): JsonResponse
    {
        try {
            $account = $this->accounts->updateStatus($id, $request->validated()['status']);

            return $this->success(
                message: "Account '{$account->status}' successfully.",
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
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

    public function togglePnd(int $id): JsonResponse
    {
        try {
            $account = $this->accounts->togglePnd($id);

            return $this->success(
                message: 'Account PND ' . ($account->pnd ? 'enabled' : 'disabled') . ' successfully.',
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
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

    public function togglePnc(int $id): JsonResponse
    {
        try {
            $account = $this->accounts->togglePnc($id);

            return $this->success(
                message: 'Account PNC ' . ($account->pnc ? 'enabled' : 'disabled') . ' successfully.',
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
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
}
