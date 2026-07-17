<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralLedger\StoreGeneralLedgerRequest;
use App\Http\Requests\Admin\GeneralLedger\UpdateGeneralLedgerRequest;
use App\Http\Requests\Admin\GeneralLedger\UpdateGeneralLedgerStatusRequest;
use App\Http\Resources\Admin\GeneralLedger\GeneralLedgerResource;
use App\Services\GeneralLedger\GeneralLedgerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GeneralLedgerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private GeneralLedgerService $generalLedgers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $generalLedgers = $this->generalLedgers->list($request->integer('per_page', 15));

        return $this->success(
            message: 'General ledgers retrieved successfully.',
            data: GeneralLedgerResource::collection($generalLedgers),
        );
    }

    public function show(int $id): JsonResponse
    {
        $generalLedger = $this->generalLedgers->find($id);

        return $this->success(
            message: 'General ledger retrieved successfully.',
            data: new GeneralLedgerResource($generalLedger),
        );
    }

    public function store(StoreGeneralLedgerRequest $request): JsonResponse
    {
        try {
            $generalLedger = $this->generalLedgers->create($request->validated());

            return $this->success(
                message: 'General ledger created successfully.',
                data: new GeneralLedgerResource($generalLedger),
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

    public function update(UpdateGeneralLedgerRequest $request, int $id): JsonResponse
    {
        $generalLedger = $this->generalLedgers->find($id);

        try {
            $generalLedger = $this->generalLedgers->update($generalLedger, $request->validated());

            return $this->success(
                message: 'General ledger updated successfully.',
                data: new GeneralLedgerResource($generalLedger),
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

    public function updateStatus(UpdateGeneralLedgerStatusRequest $request, int $id): JsonResponse
    {
        $generalLedger = $this->generalLedgers->find($id);

        try {
            $generalLedger = $this->generalLedgers->updateStatus($generalLedger, $request->validated()['status']);

            return $this->success(
                message: 'General ledger status updated successfully.',
                data: new GeneralLedgerResource($generalLedger),
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
        $generalLedger = $this->generalLedgers->find($id);

        try {
            $this->generalLedgers->delete($generalLedger);

            return $this->success(
                message: 'General ledger deleted successfully.',
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
