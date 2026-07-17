<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Currency\StoreCurrencyRequest;
use App\Http\Requests\Admin\Currency\UpdateCurrencyRequest;
use App\Http\Requests\Admin\Currency\UpdateCurrencyStatusRequest;
use App\Http\Resources\Admin\Currency\CurrencyResource;
use App\Services\Currency\CurrencyService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CurrencyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CurrencyService $currencies,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $currencies = $this->currencies->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Currencies retrieved successfully.',
            data: CurrencyResource::collection($currencies),
        );
    }

    public function show(int $id): JsonResponse
    {
        $currency = $this->currencies->find($id);

        return $this->success(
            message: 'Currency retrieved successfully.',
            data: new CurrencyResource($currency),
        );
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        try {
            $currency = $this->currencies->create($request->validated());

            return $this->success(
                message: 'Currency created successfully.',
                data: new CurrencyResource($currency),
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

    public function update(UpdateCurrencyRequest $request, int $id): JsonResponse
    {
        try {
            $currency = $this->currencies->find($id);
            $currency = $this->currencies->update($currency, $request->validated());

            return $this->success(
                message: 'Currency updated successfully.',
                data: new CurrencyResource($currency),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested currency was not found.',
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

    public function updateStatus(UpdateCurrencyStatusRequest $request, int $id): JsonResponse
    {
        try {
            $currency = $this->currencies->find($id);
            $currency = $this->currencies->updateStatus($currency, $request->validated()['status']);

            return $this->success(
                message: 'Currency status updated successfully.',
                data: new CurrencyResource($currency),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested currency was not found.',
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

    public function destroy(int $id): JsonResponse
    {
        try {
            $currency = $this->currencies->find($id);

            $this->currencies->delete($currency);

            return $this->success(
                message: 'Currency deleted successfully.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested currency was not found.',
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
