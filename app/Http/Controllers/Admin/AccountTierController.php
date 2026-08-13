<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountTier\StoreAccountTierRequest;
use App\Http\Requests\Admin\AccountTier\UpdateAccountTierRequest;
use App\Http\Resources\Admin\AccountTier\AccountTierResource;
use App\Services\AccountTier\AccountTierService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountTierController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AccountTierService $accountTiers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accountTiers = $this->accountTiers->list($request->integer('per_page', 20));

        return $this->success(
            message: 'Account tiers retrieved successfully.',
            data: AccountTierResource::collection($accountTiers),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            $accountTier = $this->accountTiers->find($id);

            return $this->success(
                message: 'Account tier retrieved successfully.',
                data: new AccountTierResource($accountTier),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account tier was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function store(StoreAccountTierRequest $request): JsonResponse
    {
        try {
            $accountTier = $this->accountTiers->create($request->validated());

            return $this->success(
                message: 'Account tier created successfully.',
                data: new AccountTierResource($accountTier),
                responseCode: '000',
                statusCode: 201,
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
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    public function update(UpdateAccountTierRequest $request, int $id): JsonResponse
    {
        try {
            $accountTier = $this->accountTiers->find($id);
            $accountTier = $this->accountTiers->update($accountTier, $request->validated());

            return $this->success(
                message: 'Account tier updated successfully.',
                data: new AccountTierResource($accountTier),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account tier was not found.',
                responseCode: '404',
                statusCode: 404,
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
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $accountTier = $this->accountTiers->find($id);
            $this->accountTiers->delete($accountTier);

            return $this->success(
                message: 'Account tier deleted successfully.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account tier was not found.',
                responseCode: '404',
                statusCode: 404,
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
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }
}
