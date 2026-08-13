<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\LogLoanCollectionRequest;
use App\Http\Resources\Admin\Loan\LoanCollectionResource;
use App\Models\LoanCollection;
use App\Services\Loan\LoanCollectionService;
use App\Services\Loan\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanCollectionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanCollectionService $collections,
        private LoanService $loans,
    ) {}

    public function index(int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);

            return $this->success(
                message: 'Collection activities retrieved successfully.',
                data: LoanCollectionResource::collection($this->collections->listForLoan($loan)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(LogLoanCollectionRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $activity = $this->collections->log($loan, $request->validated());

            return $this->success(
                message: 'Collection activity logged successfully.',
                data: new LoanCollectionResource($activity),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    // The collections queue — every overdue/due-today promise-to-pay across the whole
    // portfolio, not scoped to one loan.
    public function queue(Request $request): JsonResponse
    {
        return $this->success(
            message: 'Collections queue retrieved successfully.',
            data: LoanCollectionResource::collection($this->collections->pendingPromises($request->integer('per_page', 50))),
        );
    }

    public function markPromiseKept(int $id): JsonResponse
    {
        try {
            $activity = $this->collections->markPromiseOutcome(LoanCollection::findOrFail($id), true);

            return $this->success(message: 'Promise marked as kept.', data: new LoanCollectionResource($activity));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested collection activity was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function markPromiseBroken(int $id): JsonResponse
    {
        try {
            $activity = $this->collections->markPromiseOutcome(LoanCollection::findOrFail($id), false);

            return $this->success(message: 'Promise marked as broken.', data: new LoanCollectionResource($activity));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested collection activity was not found.', responseCode: '404', statusCode: 404);
        }
    }
}
