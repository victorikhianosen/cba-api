<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\StoreLoanClassificationRuleRequest;
use App\Http\Requests\Admin\Loan\UpdateLoanClassificationRuleRequest;
use App\Http\Resources\Admin\Loan\LoanClassificationRuleResource;
use App\Services\Loan\LoanClassificationRuleService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoanClassificationRuleController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanClassificationRuleService $rules,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            message: 'Loan classification rules retrieved successfully.',
            data: LoanClassificationRuleResource::collection($this->rules->list()),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(
                message: 'Loan classification rule retrieved successfully.',
                data: new LoanClassificationRuleResource($this->rules->find($id)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested rule was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function store(StoreLoanClassificationRuleRequest $request): JsonResponse
    {
        try {
            $rule = $this->rules->create($request->validated());

            return $this->success(
                message: 'Loan classification rule created successfully.',
                data: new LoanClassificationRuleResource($rule),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function update(UpdateLoanClassificationRuleRequest $request, int $id): JsonResponse
    {
        try {
            $rule = $this->rules->update($this->rules->find($id), $request->validated());

            return $this->success(
                message: 'Loan classification rule updated successfully.',
                data: new LoanClassificationRuleResource($rule),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested rule was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->rules->delete($this->rules->find($id));

            return $this->success(message: 'Loan classification rule deleted successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested rule was not found.', responseCode: '404', statusCode: 404);
        }
    }
}
