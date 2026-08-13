<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanGuarantorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guarantee_type'  => ['required', Rule::in(['personal', 'corporate'])],
            'customer_id'      => ['nullable', 'integer', 'exists:customers,id'],

            // Personal guarantee.
            'full_name'                => ['required_if:guarantee_type,personal', 'nullable', 'string', 'max:255'],
            'email'                    => ['required_if:guarantee_type,personal', 'nullable', 'email', 'max:255'],
            'phone'                    => ['required_if:guarantee_type,personal', 'nullable', 'string', 'max:20'],
            'role_in_business'        => ['required_if:guarantee_type,personal', 'nullable', 'string', 'max:255'],
            'id_type'                  => ['required_if:guarantee_type,personal', 'nullable', Rule::in(['nin', 'international_passport', 'drivers_license', 'voters_card'])],
            'valid_id'                => ['required_if:guarantee_type,personal', 'nullable', 'file', 'max:10240'],

            // Corporate guarantee.
            'company_name'                  => ['required_if:guarantee_type,corporate', 'nullable', 'string', 'max:255'],
            'rc_number'                      => ['required_if:guarantee_type,corporate', 'nullable', 'string', 'max:100'],
            'tax_identification_number'     => ['required_if:guarantee_type,corporate', 'nullable', 'string', 'max:100'],
            'business_address'              => ['required_if:guarantee_type,corporate', 'nullable', 'string', 'max:255'],
            'director_name'                  => ['required_if:guarantee_type,corporate', 'nullable', 'string', 'max:255'],
            'director_email'                => ['required_if:guarantee_type,corporate', 'nullable', 'email', 'max:255'],

            // Required for both guarantee types.
            'signed_guarantee_letter' => ['required', 'file', 'max:10240'],
        ];
    }
}
