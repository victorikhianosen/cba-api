<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoanProduct\StoreLoanProductRequest;
use App\Http\Requests\Admin\LoanProduct\UpdateLoanProductRequest;
use App\Http\Requests\Admin\LoanProduct\UpdateLoanProductStatusRequest;
use App\Http\Resources\Admin\LoanProduct\LoanProductResource;
use App\Services\LoanProduct\LoanProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanProductService $loanProducts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $loanProducts = $this->loanProducts->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Loan products retrieved successfully.',
            data: LoanProductResource::collection($loanProducts),
        );
    }

    public function show(int $id): JsonResponse
    {
        $loanProduct = $this->loanProducts->find($id);

        return $this->success(
            message: 'Loan product retrieved successfully.',
            data: new LoanProductResource($loanProduct),
        );
    }

    public function store(StoreLoanProductRequest $request): JsonResponse
    {
        try {
            $loanProduct = $this->loanProducts->create($request->toLoanProductData());

            return $this->success(
                message: 'Loan product created successfully.',
                data: new LoanProductResource($loanProduct),
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

    public function update(UpdateLoanProductRequest $request, int $id): JsonResponse
    {
        $loanProduct = $this->loanProducts->find($id);

        try {
            $loanProduct = $this->loanProducts->update($loanProduct, $request->validated());

            return $this->success(
                message: 'Loan product updated successfully.',
                data: new LoanProductResource($loanProduct),
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
        $loanProduct = $this->loanProducts->find($id);

        try {
            $loanProduct = $this->loanProducts->approve($loanProduct);

            return $this->success(
                message: 'Loan product approved successfully.',
                data: new LoanProductResource($loanProduct),
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

    public function updateStatus(UpdateLoanProductStatusRequest $request, int $id): JsonResponse
    {
        $loanProduct = $this->loanProducts->find($id);

        try {
            $loanProduct = $this->loanProducts->updateStatus($loanProduct, $request->validated()['status']);

            return $this->success(
                message: 'Loan product status updated successfully.',
                data: new LoanProductResource($loanProduct),
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
        $loanProduct = $this->loanProducts->find($id);

        try {
            $this->loanProducts->delete($loanProduct);

            return $this->success(
                message: 'Loan product deleted successfully.',
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
