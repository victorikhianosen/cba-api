<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\PreviewLoanTopupRequest;
use App\Http\Requests\Admin\Loan\RejectLoanTopupRequest;
use App\Http\Requests\Admin\Loan\RequestLoanTopupRequest;
use App\Http\Resources\Admin\Loan\LoanTopupResource;
use App\Services\Loan\LoanService;
use App\Services\Loan\LoanTopupService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanTopupController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanTopupService $topups,
        private LoanService $loans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            message: 'Loan top-ups retrieved successfully.',
            data: LoanTopupResource::collection($this->topups->list(
                $request->integer('per_page', 50),
                $request->string('status')->trim()->value() ?: null,
            )),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(message: 'Top-up retrieved successfully.', data: new LoanTopupResource($this->topups->find($id)));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested top-up was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(RequestLoanTopupRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $topup = $this->topups->request($loan, $request->validated());

            return $this->success(
                message: 'Top-up requested successfully.',
                data: new LoanTopupResource($topup),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function previewSchedule(PreviewLoanTopupRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);

            return $this->success(
                message: 'Top-up preview computed successfully.',
                data: $this->topups->preview($loan, $request->validated()),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $topup = $this->topups->approve($this->topups->find($id));

            return $this->success(message: 'Top-up approved successfully.', data: new LoanTopupResource($topup));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested top-up was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function reject(RejectLoanTopupRequest $request, int $id): JsonResponse
    {
        try {
            $topup = $this->topups->reject($this->topups->find($id), $request->validated()['reason']);

            return $this->success(message: 'Top-up rejected successfully.', data: new LoanTopupResource($topup));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested top-up was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }
}
