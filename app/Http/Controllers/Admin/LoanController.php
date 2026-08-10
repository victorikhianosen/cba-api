<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\PreviewLoanScheduleRequest;
use App\Http\Requests\Admin\Loan\RejectLoanRequest;
use App\Http\Requests\Admin\Loan\RepayLoanRequest;
use App\Http\Requests\Admin\Loan\StoreLoanRequest;
use App\Http\Resources\Admin\Loan\LoanResource;
use App\Http\Resources\Admin\Loan\LoanScheduleResource;
use App\Http\Resources\Admin\Loan\LoanTransactionResource;
use App\Models\Loan;
use App\Services\Loan\LoanRepaymentService;
use App\Services\Loan\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanService $loans,
        private LoanRepaymentService $repayments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $loans = $this->loans->list(
            $request->integer('per_page', 50),
            $request->string('search')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
            $request->integer('branch_id') ?: null,
            $request->integer('loan_officer_id') ?: null,
        );

        return $this->success(
            message: 'Loans retrieved successfully.',
            data: LoanResource::collection($loans),
            meta: [
                'statistics' => [
                    'total_loans'    => Loan::query()->count(),
                    'total_active'   => Loan::query()->where('status', 'active')->count(),
                    'total_pending'  => Loan::query()->where('status', 'pending')->count(),
                    'total_written_off' => Loan::query()->where('status', 'written_off')->count(),
                ],
            ],
        );
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        try {
            $loan = $this->loans->open($request->validated());

            return $this->success(
                message: 'Loan booked successfully.',
                data: new LoanResource($loan),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan product or disbursement account was not found.',
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

    public function show(int $id): JsonResponse
    {
        try {
            $loan = $this->loans->find($id);

            return $this->success(
                message: 'Loan retrieved successfully.',
                data: new LoanResource($loan),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function transactions(Request $request, int $id): JsonResponse
    {
        try {
            $transactions = $this->loans->transactionsFor($id, $request->integer('per_page', 20));

            return $this->success(
                message: 'Loan transactions retrieved successfully.',
                data: LoanTransactionResource::collection($transactions),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function schedule(int $id): JsonResponse
    {
        try {
            $schedule = $this->loans->currentScheduleFor($id);

            return $this->success(
                message: 'Loan schedule retrieved successfully.',
                data: LoanScheduleResource::collection($schedule),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function previewSchedule(PreviewLoanScheduleRequest $request): JsonResponse
    {
        try {
            $preview = $this->loans->previewSchedule($request->validated());

            return $this->success(
                message: 'Schedule preview generated successfully.',
                data: $preview,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan product was not found.',
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

    public function approve(int $id): JsonResponse
    {
        try {
            $loan = $this->loans->approve($this->loans->find($id));

            return $this->success(
                message: 'Loan approved and disbursed successfully.',
                data: new LoanResource($loan),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan was not found.',
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

    public function reject(RejectLoanRequest $request, int $id): JsonResponse
    {
        try {
            $loan = $this->loans->reject($this->loans->find($id), $request->validated()['reason']);

            return $this->success(
                message: 'Loan rejected successfully.',
                data: new LoanResource($loan),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan was not found.',
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

    public function previewRepayment(Request $request, int $id): JsonResponse
    {
        try {
            $loan = $this->loans->find($id);
            $preview = $this->repayments->preview($loan, (string) $request->input('amount'));

            return $this->success(
                message: 'Repayment allocation preview generated successfully.',
                data: $preview,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function repay(RepayLoanRequest $request, int $id): JsonResponse
    {
        try {
            $loan = $this->loans->find($id);
            $data = $request->validated();

            $result = $this->repayments->record(
                $loan,
                (string) $data['amount'],
                (int) $data['funding_account_id'],
                $data['description'] ?? null,
            );

            return $this->success(
                message: 'Repayment recorded successfully.',
                data: [
                    'loan'          => new LoanResource($result['loan']),
                    'transactions'  => LoanTransactionResource::collection($result['transactions']),
                ],
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested loan or funding account was not found.',
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
}
