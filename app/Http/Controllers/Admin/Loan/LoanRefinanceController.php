<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\RejectLoanRefinanceRequest;
use App\Http\Requests\Admin\Loan\RequestLoanRefinanceRequest;
use App\Http\Resources\Admin\Loan\LoanRefinanceResource;
use App\Services\Loan\LoanRefinanceService;
use App\Services\Loan\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanRefinanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanRefinanceService $refinances,
        private LoanService $loans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            message: 'Loan refinances retrieved successfully.',
            data: LoanRefinanceResource::collection($this->refinances->list(
                $request->integer('per_page', 50),
                $request->string('status')->trim()->value() ?: null,
            )),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(message: 'Refinance retrieved successfully.', data: new LoanRefinanceResource($this->refinances->find($id)));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested refinance was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(RequestLoanRefinanceRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $refinance = $this->refinances->request($loan, $request->validated());

            return $this->success(
                message: 'Refinance requested successfully.',
                data: new LoanRefinanceResource($refinance),
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
            $refinance = $this->refinances->approve($this->refinances->find($id));

            return $this->success(message: 'Refinance approved successfully.', data: new LoanRefinanceResource($refinance));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested refinance was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function reject(RejectLoanRefinanceRequest $request, int $id): JsonResponse
    {
        try {
            $refinance = $this->refinances->reject($this->refinances->find($id), $request->validated()['reason']);

            return $this->success(message: 'Refinance rejected successfully.', data: new LoanRefinanceResource($refinance));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested refinance was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }
}
