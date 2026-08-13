<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Loan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loan\ClaimLoanGuarantorRequest;
use App\Http\Requests\Admin\Loan\RejectLoanGuarantorRequest;
use App\Http\Requests\Admin\Loan\StoreLoanGuarantorRequest;
use App\Services\Loan\LoanGuarantorService;
use App\Services\Loan\LoanService;
use App\Http\Resources\Admin\Loan\LoanGuarantorResource;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoanGuarantorController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoanGuarantorService $guarantors,
        private LoanService $loans,
    ) {}

    public function index(int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);

            return $this->success(
                message: 'Loan guarantors retrieved successfully.',
                data: LoanGuarantorResource::collection($this->guarantors->listForLoan($loan)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    // Blank, fillable guarantee letter template — downloaded, signed, then re-uploaded
    // as signed_guarantee_letter when the guarantor is added. Not tied to a specific
    // loan/guarantor, so no loan_id in the route.
    public function downloadTemplate(Request $request)
    {
        $guaranteeType = $request->string('type')->value() === 'corporate' ? 'corporate' : 'personal';
        $pdf = $this->guarantors->renderGuaranteeLetterTemplate($guaranteeType);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$guaranteeType}_guarantee_letter_template.pdf\"",
        ]);
    }

    public function store(StoreLoanGuarantorRequest $request, int $loanId): JsonResponse
    {
        try {
            $loan = $this->loans->find($loanId);
            $guarantor = $this->guarantors->create($loan, $request->validated());

            return $this->success(
                message: 'Guarantor added successfully.',
                data: new LoanGuarantorResource($guarantor),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested loan was not found.', responseCode: '404', statusCode: 404);
        }
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $guarantor = $this->guarantors->approve($this->guarantors->find($id));

            return $this->success(message: 'Guarantor approved successfully.', data: new LoanGuarantorResource($guarantor));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested guarantor was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function reject(RejectLoanGuarantorRequest $request, int $id): JsonResponse
    {
        try {
            $guarantor = $this->guarantors->reject($this->guarantors->find($id), $request->validated()['reason']);

            return $this->success(message: 'Guarantor rejected successfully.', data: new LoanGuarantorResource($guarantor));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested guarantor was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function release(int $id): JsonResponse
    {
        try {
            $guarantor = $this->guarantors->release($this->guarantors->find($id));

            return $this->success(message: 'Guarantor released successfully.', data: new LoanGuarantorResource($guarantor));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested guarantor was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }

    public function claim(ClaimLoanGuarantorRequest $request, int $id): JsonResponse
    {
        try {
            $guarantor = $this->guarantors->claim($this->guarantors->find($id), (string) $request->validated()['amount']);

            return $this->success(message: 'Guarantee claim recorded successfully.', data: new LoanGuarantorResource($guarantor));
        } catch (ModelNotFoundException $e) {
            return $this->error(message: 'The requested guarantor was not found.', responseCode: '404', statusCode: 404);
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        }
    }
}
