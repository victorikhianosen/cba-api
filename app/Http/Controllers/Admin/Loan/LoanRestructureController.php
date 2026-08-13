<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\PreviewLoanRestructureRequest;
use App\Http\Requests\Admin\Loan\RejectLoanRestructureRequest;
use App\Http\Requests\Admin\Loan\RequestLoanRestructureRequest;
use App\Http\Resources\Admin\Loan\LoanRestructureResource;
use App\Services\Loan\LoanRestructureService;
use App\Services\Loan\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanRestructureController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanRestructureService $restructures,
        private LoanService $loans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            message: 'Loan restructures retrieved successfully.',
            data: LoanRestructureResource::collection($this->restructures->list(
                $request->integer('per_page', 50),
                $request->string('status')->trim()->value() ?: null,
            )),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(message: 'Restructure retrieved successfully.', data: new LoanRestructureResource($this->restructures->find($id)));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested restructure was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(RequestLoanRestructureRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $restructure = $this->restructures->request($loan, $request->validated());

            return $this->success(
                message: 'Restructure requested successfully.',
                data: new LoanRestructureResource($restructure),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function previewSchedule(PreviewLoanRestructureRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);

            return $this->success(
                message: 'Restructure preview computed successfully.',
                data: $this->restructures->preview($loan, $request->validated()),
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
            $restructure = $this->restructures->approve($this->restructures->find($id));

            return $this->success(message: 'Restructure approved successfully.', data: new LoanRestructureResource($restructure));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested restructure was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function reject(RejectLoanRestructureRequest $request, int $id): JsonResponse
    {
        try {
            $restructure = $this->restructures->reject($this->restructures->find($id), $request->validated()['reason']);

            return $this->success(message: 'Restructure rejected successfully.', data: new LoanRestructureResource($restructure));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested restructure was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }
}
