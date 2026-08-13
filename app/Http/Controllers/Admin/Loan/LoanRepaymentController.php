<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\RejectLoanRepaymentRequest;
use App\Http\Requests\Admin\Loan\RequestLoanRepaymentRequest;
use App\Http\Resources\Admin\Loan\LoanRepaymentResource;
use App\Services\Loan\LoanRepaymentService;
use App\Services\Loan\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanRepaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanRepaymentService $repayments,
        private LoanService $loans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            message: 'Loan repayments retrieved successfully.',
            data: LoanRepaymentResource::collection($this->repayments->list(
                $request->integer('per_page', 50),
                $request->string('status')->trim()->value() ?: null,
            )),
        );
    }

    public function forLoan(int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);

            return $this->success(
                message: 'Loan repayments retrieved successfully.',
                data: LoanRepaymentResource::collection($this->repayments->listForLoan($loan)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(message: 'Repayment retrieved successfully.', data: new LoanRepaymentResource($this->repayments->find($id)));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested repayment was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function previewSchedule(Request $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $preview = $this->repayments->preview($loan, (string) $request->input('amount'));

            return $this->success(
                message: 'Repayment allocation preview generated successfully.',
                data: $preview,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(RequestLoanRepaymentRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $data = $request->validated();

            $repayment = $this->repayments->request(
                $loan,
                (string) $data['amount'],
                (int) $data['funding_account_id'],
                $data['description'] ?? null,
            );

            return $this->success(
                message: 'Repayment requested successfully.',
                data: new LoanRepaymentResource($repayment),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan or funding account was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $repayment = $this->repayments->approve($this->repayments->find($id));

            return $this->success(message: 'Repayment approved successfully.', data: new LoanRepaymentResource($repayment));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested repayment was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function reject(RejectLoanRepaymentRequest $request, int $id): JsonResponse
    {
        try {
            $repayment = $this->repayments->reject($this->repayments->find($id), $request->validated()['reason']);

            return $this->success(message: 'Repayment rejected successfully.', data: new LoanRepaymentResource($repayment));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested repayment was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }
}
