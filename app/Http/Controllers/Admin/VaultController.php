<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Vault\StoreVaultRequest;
use App\Http\Requests\Admin\Vault\UpdateVaultRequest;
use App\Http\Requests\Admin\Vault\UpdateVaultStatusRequest;
use App\Http\Resources\Admin\Vault\VaultResource;
use App\Services\Vault\VaultService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VaultController extends Controller
{
    use ApiResponse;

    public function __construct(
        private VaultService $vaults,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $vaults = $this->vaults->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Vaults retrieved successfully.',
            data: VaultResource::collection($vaults),
        );
    }

    public function show(int $id): JsonResponse
    {
        $vault = $this->vaults->find($id);

        return $this->success(
            message: 'Vault retrieved successfully.',
            data: new VaultResource($vault),
        );
    }

    public function store(StoreVaultRequest $request): JsonResponse
    {
        try {
            $vault = $this->vaults->create($request->validated());

            return $this->success(
                message: 'Vault created successfully.',
                data: new VaultResource($vault),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function update(UpdateVaultRequest $request, int $id): JsonResponse
    {
        $vault = $this->vaults->find($id);

        try {
            $vault = $this->vaults->update($vault, $request->validated());

            return $this->success(message: 'Vault updated successfully.', data: new VaultResource($vault));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        $vault = $this->vaults->find($id);

        try {
            $vault = $this->vaults->approve($vault);

            return $this->success(message: 'Vault approved successfully.', data: new VaultResource($vault));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function updateStatus(UpdateVaultStatusRequest $request, int $id): JsonResponse
    {
        $vault = $this->vaults->find($id);

        try {
            $vault = $this->vaults->updateStatus($vault, $request->validated()['status']);

            return $this->success(message: 'Vault status updated successfully.', data: new VaultResource($vault));
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }
}
