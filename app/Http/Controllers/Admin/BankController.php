<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Bank\StoreBankRequest;
use App\Http\Requests\Admin\Bank\UpdateBankRequest;
use App\Http\Requests\Admin\Bank\UploadBankLogoRequest;
use App\Http\Resources\Admin\Bank\BankResource;
use App\Services\Audit\AuditService;
use App\Services\Bank\BankService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BankService $banks,
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $banks = $this->banks->list(
            $request->integer('per_page', 20),
            $request->string('search')->value() ?: null,
        );

        return $this->success(
            message: 'Banks retrieved successfully.',
            data: BankResource::collection($banks),
        );
    }

    public function show(int $id): JsonResponse
    {
        $bank = $this->banks->find($id);

        return $this->success(
            message: 'Bank retrieved successfully.',
            data: new BankResource($bank),
        );
    }

    public function store(StoreBankRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            unset($data['logo']);

            $bank = $this->banks->create($data, $request->file('logo'));

            $this->audit->log(
                action: 'created',
                module: 'banks',
                auditable: $bank,
                after: $bank->toArray(),
                description: "Bank '{$bank->bank_name}' ({$bank->bank_code}) created.",
            );

            return $this->success(
                message: 'Bank created successfully.',
                data: new BankResource($bank),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: app()->isLocal() ? $e->getMessage() : 'Could not create bank.',
                responseCode: '100',
                statusCode: 500,
            );
        }
    }

    public function update(UpdateBankRequest $request, int $id): JsonResponse
    {
        $bank = $this->banks->find($id);

        try {
            $before = $bank->toArray();

            $bank = $this->banks->update($bank, $request->validated());

            $this->audit->log(
                action: 'updated',
                module: 'banks',
                auditable: $bank,
                before: $before,
                after: $bank->toArray(),
                description: "Bank '{$bank->bank_name}' ({$bank->bank_code}) updated.",
            );

            return $this->success(
                message: 'Bank updated successfully.',
                data: new BankResource($bank),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: app()->isLocal() ? $e->getMessage() : 'Could not update bank.',
                responseCode: '100',
                statusCode: 500,
            );
        }
    }

    public function uploadLogo(UploadBankLogoRequest $request, int $id): JsonResponse
    {
        try {
            $bank = $this->banks->find($id);
            $bank = $this->banks->uploadLogo($bank, $request->file('logo'));

            $this->audit->log(
                action: 'logo_updated',
                module: 'banks',
                auditable: $bank,
                description: "Logo for bank '{$bank->bank_name}' ({$bank->bank_code}) was updated.",
            );

            return $this->success(
                message: 'Bank logo updated successfully.',
                data: new BankResource($bank),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested bank was not found.',
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
            $bank = $this->banks->find($id);
            $name = $bank->bank_name;
            $code = $bank->bank_code;

            $this->banks->delete($bank);

            $this->audit->log(
                action: 'deleted',
                module: 'banks',
                before: ['bank_name' => $name, 'bank_code' => $code],
                description: "Bank '{$name}' ({$code}) deleted.",
            );

            return $this->success(
                message: 'Bank deleted successfully.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested bank was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to delete the bank. Please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }
}
