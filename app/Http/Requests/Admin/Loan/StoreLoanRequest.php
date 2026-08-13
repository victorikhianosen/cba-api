<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_product_id'          => ['required', 'integer', 'exists:loan_products,id'],
            'disbursement_account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'principal_amount'         => ['required', 'numeric', 'min:0.01'],
            'duration'     => ['required', 'integer', 'min:1', 'max:24'],
            // Optional overrides — default to the disbursement account's own customer's
            // branch/account officer when omitted (see LoanService::open()).
            'branch_id'                => ['nullable', 'integer', 'exists:branches,id'],
            'loan_officer_id'          => ['nullable', 'integer', 'exists:account_officers,id'],

            // General-purpose attachment — any file type accepted.
            'loan_file'                => ['nullable', 'file', 'max:10240'],

            // Optional at booking time — same field shapes as
            // StoreLoanCollateralRequest/StoreLoanGuarantorRequest, nested under an array
            // so multiple of each can be pledged/added in the same booking request. File
            // uploads are optional here (unlike the standalone add-guarantor flow) since
            // this is a fast-path convenience — documents can still be attached later.
            'collaterals'                          => ['nullable', 'array'],
            'collaterals.*.type'                   => ['required', 'string', 'max:100'],
            'collaterals.*.description'            => ['required', 'string'],
            'collaterals.*.estimated_amount'       => ['nullable', 'numeric', 'min:0'],
            'collaterals.*.proof_of_ownership'     => ['nullable', 'file', 'max:10240'],

            'guarantors'                                       => ['nullable', 'array'],
            'guarantors.*.guarantee_type'                      => ['required', 'in:personal,corporate'],
            'guarantors.*.customer_id'                          => ['nullable', 'integer', 'exists:customers,id'],
            'guarantors.*.full_name'                            => ['required_if:guarantors.*.guarantee_type,personal', 'nullable', 'string', 'max:255'],
            'guarantors.*.email'                                => ['required_if:guarantors.*.guarantee_type,personal', 'nullable', 'email', 'max:255'],
            'guarantors.*.phone'                                => ['required_if:guarantors.*.guarantee_type,personal', 'nullable', 'string', 'max:20'],
            'guarantors.*.role_in_business'                    => ['required_if:guarantors.*.guarantee_type,personal', 'nullable', 'string', 'max:255'],
            'guarantors.*.id_type'                              => ['nullable', Rule::in(['nin', 'international_passport', 'drivers_license', 'voters_card'])],
            'guarantors.*.valid_id'                            => ['nullable', 'file', 'max:10240'],
            'guarantors.*.company_name'                        => ['required_if:guarantors.*.guarantee_type,corporate', 'nullable', 'string', 'max:255'],
            'guarantors.*.rc_number'                            => ['required_if:guarantors.*.guarantee_type,corporate', 'nullable', 'string', 'max:100'],
            'guarantors.*.tax_identification_number'          => ['required_if:guarantors.*.guarantee_type,corporate', 'nullable', 'string', 'max:100'],
            'guarantors.*.business_address'                    => ['required_if:guarantors.*.guarantee_type,corporate', 'nullable', 'string', 'max:255'],
            'guarantors.*.director_name'                        => ['required_if:guarantors.*.guarantee_type,corporate', 'nullable', 'string', 'max:255'],
            'guarantors.*.director_email'                      => ['required_if:guarantors.*.guarantee_type,corporate', 'nullable', 'email', 'max:255'],
            'guarantors.*.signed_guarantee_letter'            => ['nullable', 'file', 'max:10240'],
        ];
    }
}
