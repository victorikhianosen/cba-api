<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\RecordLoanRecoveryRequest;
use App\Http\Resources\Admin\Loan\LoanRecoveryResource;
use App\Services\Loan\LoanRecoveryService;
use App\Services\Loan\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoanRecoveryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanRecoveryService $recoveries,
        private LoanService $loans,
    ) {}

    public function index(int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);

            return $this->success(
                message: 'Loan recoveries retrieved successfully.',
                data: LoanRecoveryResource::collection($this->recoveries->listForLoan($loan)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(RecordLoanRecoveryRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $recovery = $this->recoveries->record($loan, $request->validated());

            return $this->success(
                message: 'Recovery recorded successfully.',
                data: new LoanRecoveryResource($recovery),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan or funding account was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }
}
