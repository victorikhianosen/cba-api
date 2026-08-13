<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\RejectLoanWriteOffRequest;
use App\Http\Requests\Admin\Loan\RequestLoanWriteOffRequest;
use App\Http\Resources\Admin\Loan\LoanWriteOffResource;
use App\Services\Loan\LoanService;
use App\Services\Loan\LoanWriteOffService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanWriteOffController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanWriteOffService $writeOffs,
        private LoanService $loans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            message: 'Loan write-offs retrieved successfully.',
            data: LoanWriteOffResource::collection($this->writeOffs->list(
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
                message: 'Loan write-offs retrieved successfully.',
                data: LoanWriteOffResource::collection($this->writeOffs->listForLoan($loan)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(message: 'Write-off retrieved successfully.', data: new LoanWriteOffResource($this->writeOffs->find($id)));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested write-off was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(RequestLoanWriteOffRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $writeOff = $this->writeOffs->request($loan, $request->validated()['reason']);

            return $this->success(
                message: 'Write-off requested successfully.',
                data: new LoanWriteOffResource($writeOff),
                responseCode: '000',
                statusCode: 201,
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
            $writeOff = $this->writeOffs->approve($this->writeOffs->find($id));

            return $this->success(message: 'Write-off approved successfully.', data: new LoanWriteOffResource($writeOff));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested write-off was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function reject(RejectLoanWriteOffRequest $request, int $id): JsonResponse
    {
        try {
            $writeOff = $this->writeOffs->reject($this->writeOffs->find($id), $request->validated()['reason']);

            return $this->success(message: 'Write-off rejected successfully.', data: new LoanWriteOffResource($writeOff));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested write-off was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }
}
