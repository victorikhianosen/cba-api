<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountProduct\StoreAccountProductRequest;
use App\Http\Requests\Admin\AccountProduct\UpdateAccountProductRequest;
use App\Http\Requests\Admin\AccountProduct\UpdateAccountProductStatusRequest;
use App\Http\Resources\Admin\AccountProduct\AccountProductResource;
use App\Services\AccountProduct\AccountProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AccountProductService $accountProducts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accountProducts = $this->accountProducts->list($request->integer('per_page', 15), ProductCategory::DEPOSIT->productTypes());

        return $this->success(
            message: 'Account products retrieved successfully.',
            data: AccountProductResource::collection($accountProducts),
        );
    }

    public function show(int $id): JsonResponse
    {
        $accountProduct = $this->accountProducts->find($id, ProductCategory::DEPOSIT->productTypes());

        return $this->success(
            message: 'Account product retrieved successfully.',
            data: new AccountProductResource($accountProduct),
        );
    }

    public function store(StoreAccountProductRequest $request): JsonResponse
    {
        try {
            $accountProduct = $this->accountProducts->create($request->toAccountProductData());

            return $this->success(
                message: 'Account product created successfully.',
                data: new AccountProductResource($accountProduct),
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

    public function update(UpdateAccountProductRequest $request, int $id): JsonResponse
    {
        $accountProduct = $this->accountProducts->find($id, ProductCategory::DEPOSIT->productTypes());

        try {
            $accountProduct = $this->accountProducts->update($accountProduct, $request->validated());

            return $this->success(
                message: 'Account product updated successfully.',
                data: new AccountProductResource($accountProduct),
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

    public function approve(int $id): JsonResponse
    {
        $accountProduct = $this->accountProducts->find($id, ProductCategory::DEPOSIT->productTypes());

        try {
            $accountProduct = $this->accountProducts->approve($accountProduct);

            return $this->success(
                message: 'Account product approved successfully.',
                data: new AccountProductResource($accountProduct),
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

    public function updateStatus(UpdateAccountProductStatusRequest $request, int $id): JsonResponse
    {
        $accountProduct = $this->accountProducts->find($id, ProductCategory::DEPOSIT->productTypes());

        try {
            $accountProduct = $this->accountProducts->updateStatus($accountProduct, $request->validated()['status']);

            return $this->success(
                message: 'Account product status updated successfully.',
                data: new AccountProductResource($accountProduct),
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
        $accountProduct = $this->accountProducts->find($id, ProductCategory::DEPOSIT->productTypes());

        try {
            $this->accountProducts->delete($accountProduct);

            return $this->success(
                message: 'Account product deleted successfully.',
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
