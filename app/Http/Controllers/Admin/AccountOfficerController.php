<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountOfficer\StoreAccountOfficerRequest;
use App\Http\Requests\Admin\AccountOfficer\UpdateAccountOfficerRequest;
use App\Http\Requests\Admin\AccountOfficer\UpdateAccountOfficerStatusRequest;
use App\Http\Resources\Admin\AccountOfficer\AccountOfficerResource;
use App\Services\AccountOfficer\AccountOfficerService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountOfficerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AccountOfficerService $officers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $officers = $this->officers->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Account officers retrieved successfully.',
            data: AccountOfficerResource::collection($officers),
        );
    }

    public function show(int $id): JsonResponse
    {
        $officer = $this->officers->find($id);

        return $this->success(
            message: 'Account officer retrieved successfully.',
            data: new AccountOfficerResource($officer),
        );
    }

    public function store(StoreAccountOfficerRequest $request): JsonResponse
    {
        try {
            $officer = $this->officers->create($request->validated());

            return $this->success(
                message: 'Account officer created successfully.',
                data: new AccountOfficerResource($officer),
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

    public function update(UpdateAccountOfficerRequest $request, int $id): JsonResponse
    {
        try {
            $officer = $this->officers->find($id);
            $officer = $this->officers->update($officer, $request->validated());

            return $this->success(
                message: 'Account officer updated successfully.',
                data: new AccountOfficerResource($officer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account officer was not found.',
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
            $officer = $this->officers->find($id);

            $this->officers->delete($officer);

            return $this->success(
                message: 'Account officer deleted successfully.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account officer was not found.',
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
