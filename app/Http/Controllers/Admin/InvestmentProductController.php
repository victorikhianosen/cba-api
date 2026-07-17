<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvestmentProduct\StoreInvestmentProductRequest;
use App\Http\Requests\Admin\InvestmentProduct\UpdateInvestmentProductRequest;
use App\Http\Requests\Admin\InvestmentProduct\UpdateInvestmentProductStatusRequest;
use App\Http\Resources\Admin\InvestmentProduct\InvestmentProductResource;
use App\Services\InvestmentProduct\InvestmentProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvestmentProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private InvestmentProductService $investmentProducts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $investmentProducts = $this->investmentProducts->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Investment products retrieved successfully.',
            data: InvestmentProductResource::collection($investmentProducts),
        );
    }

    public function show(int $id): JsonResponse
    {
        $investmentProduct = $this->investmentProducts->find($id);

        return $this->success(
            message: 'Investment product retrieved successfully.',
            data: new InvestmentProductResource($investmentProduct),
        );
    }

    public function store(StoreInvestmentProductRequest $request): JsonResponse
    {
        try {
            $investmentProduct = $this->investmentProducts->create($request->toInvestmentProductData());

            return $this->success(
                message: 'Investment product created successfully.',
                data: new InvestmentProductResource($investmentProduct),
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

    public function update(UpdateInvestmentProductRequest $request, int $id): JsonResponse
    {
        $investmentProduct = $this->investmentProducts->find($id);

        try {
            $investmentProduct = $this->investmentProducts->update($investmentProduct, $request->validated());

            return $this->success(
                message: 'Investment product updated successfully.',
                data: new InvestmentProductResource($investmentProduct),
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
        $investmentProduct = $this->investmentProducts->find($id);

        try {
            $investmentProduct = $this->investmentProducts->approve($investmentProduct);

            return $this->success(
                message: 'Investment product approved successfully.',
                data: new InvestmentProductResource($investmentProduct),
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

    public function updateStatus(UpdateInvestmentProductStatusRequest $request, int $id): JsonResponse
    {
        $investmentProduct = $this->investmentProducts->find($id);

        try {
            $investmentProduct = $this->investmentProducts->updateStatus($investmentProduct, $request->validated()['status']);

            return $this->success(
                message: 'Investment product status updated successfully.',
                data: new InvestmentProductResource($investmentProduct),
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
        $investmentProduct = $this->investmentProducts->find($id);

        try {
            $this->investmentProducts->delete($investmentProduct);

            return $this->success(
                message: 'Investment product deleted successfully.',
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
