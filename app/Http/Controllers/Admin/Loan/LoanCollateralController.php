<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\RealizeLoanCollateralRequest;
use App\Http\Requests\Admin\Loan\ReleaseLoanCollateralRequest;
use App\Http\Requests\Admin\Loan\StoreLoanCollateralRequest;
use App\Http\Requests\Admin\Loan\UpdateLoanCollateralRequest;
use App\Http\Resources\Admin\Loan\LoanCollateralResource;
use App\Services\Loan\LoanCollateralService;
use App\Services\Loan\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoanCollateralController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanCollateralService $collaterals,
        private LoanService $loans,
    ) {}

    public function index(int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);

            return $this->success(
                message: 'Loan collaterals retrieved successfully.',
                data: LoanCollateralResource::collection($this->collaterals->listForLoan($loan)->load('loan')),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(StoreLoanCollateralRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $collateral = $this->collaterals->create($loan, $request->validated());

            return $this->success(
                message: 'Collateral added successfully.',
                data: new LoanCollateralResource($collateral->load('loan')),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function update(UpdateLoanCollateralRequest $request, int $id): JsonResponse
    {
        try {
            $collateral = $this->collaterals->update($this->collaterals->find($id), $request->validated());

            return $this->success(message: 'Collateral updated successfully.', data: new LoanCollateralResource($collateral->load('loan')));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested collateral was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function release(ReleaseLoanCollateralRequest $request, int $id): JsonResponse
    {
        try {
            $collateral = $this->collaterals->release($this->collaterals->find($id), $request->validated()['reason']);

            return $this->success(message: 'Collateral released successfully.', data: new LoanCollateralResource($collateral->load('loan')));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested collateral was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function realize(RealizeLoanCollateralRequest $request, int $id): JsonResponse
    {
        try {
            $collateral = $this->collaterals->realize($this->collaterals->find($id), (string) $request->validated()['amount']);

            return $this->success(message: 'Collateral realization recorded successfully.', data: new LoanCollateralResource($collateral->load('loan')));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested collateral was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }
}
