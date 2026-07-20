<?php

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'           => ['nullable', 'integer', 'exists:branches,id'],
            'account_officer_id'  => ['nullable', 'integer', 'exists:account_officers,id'],
            'customer_type'       => ['sometimes', 'in:individual,business,minor'],
            'guardian_id'         => ['nullable', 'integer', 'exists:customers,id'],

            'title'          => ['nullable', 'string', 'max:20'],
            'first_name'     => ['required_if:customer_type,individual,minor', 'nullable', 'string', 'max:255'],
            'middle_name'    => ['nullable', 'string', 'max:255'],
            'last_name'      => ['required_if:customer_type,individual,minor', 'nullable', 'string', 'max:255'],

            'phone'    => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:customers,username'],

            'password'       => ['nullable', 'string', 'min:6'],
            'panic_password' => ['nullable', 'string', 'min:6'],
            'pin'            => ['nullable', 'digits:4'],

            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'gender'         => ['nullable', 'in:male,female,other'],
            'dob'            => ['nullable', 'date', 'before:today'],

            'occupation'      => ['nullable', 'string', 'max:255'],
            'working_status'  => ['nullable', 'string', 'max:100'],
            'referral_code'   => ['nullable', 'string', 'max:50', 'unique:customers,referral_code'],

            'bvn'         => ['nullable', 'digits:11', 'unique:customers,bvn'],
            'nin_number'  => ['nullable', 'digits:11', 'unique:customers,nin_number'],
            'tin'         => ['nullable', 'string', 'max:50', 'unique:customers,tin'],

            'is_staff' => ['sometimes', 'boolean'],
            'pep'      => ['sometimes', 'boolean'],

            'enable_internet_bank'  => ['sometimes', 'boolean'],
            'enable_sms'            => ['sometimes', 'boolean'],
            'enable_email'          => ['sometimes', 'boolean'],
            'enable_reset_password' => ['sometimes', 'boolean'],
            'enable_panic_password' => ['sometimes', 'boolean'],

            'mother_maiden_name' => ['nullable', 'string', 'max:255'],
            'spouse_name'        => ['nullable', 'string', 'max:255'],

            'documents'              => ['sometimes', 'nullable', 'array'],
            'documents.*.title'      => ['nullable', 'string', 'max:255'],
            'documents.*.name'       => ['nullable', 'string', 'max:255'],
            'documents.*.path'       => ['nullable', 'string', 'max:500'],
            'documents.*.type'       => ['nullable', 'string', 'max:100'],

            // Addresses — shared by individual and business customers.
            'addresses'                  => ['sometimes', 'nullable', 'array'],
            'addresses.*.type'           => ['nullable', 'string', 'max:50'],
            'addresses.*.address'        => ['required', 'string', 'max:255'],
            'addresses.*.lgs'            => ['nullable', 'string', 'max:100'],
            'addresses.*.city'           => ['nullable', 'string', 'max:100'],
            'addresses.*.state'          => ['nullable', 'string', 'max:100'],
            'addresses.*.country'        => ['nullable', 'string', 'max:100'],
            'addresses.*.landmark'       => ['nullable', 'string', 'max:255'],
            'addresses.*.longitude'      => ['nullable', 'string', 'max:50'],
            'addresses.*.latitude'       => ['nullable', 'string', 'max:50'],
            'addresses.*.is_primary'     => ['sometimes', 'boolean'],

            // Next of kin — individual/minor customers only.
            'next_of_kin'                    => ['sometimes', 'nullable', 'array', 'prohibited_if:customer_type,business'],
            'next_of_kin.*.title'            => ['nullable', 'string', 'max:20'],
            'next_of_kin.*.first_name'       => ['required', 'string', 'max:255'],
            'next_of_kin.*.middle_name'      => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.last_name'        => ['required', 'string', 'max:255'],
            'next_of_kin.*.relationship'     => ['required', 'string', 'max:100'],
            'next_of_kin.*.gender'           => ['nullable', 'in:male,female,other'],
            'next_of_kin.*.dob'              => ['nullable', 'date', 'before:today'],
            'next_of_kin.*.phone'            => ['required', 'string', 'max:20'],
            'next_of_kin.*.email'            => ['nullable', 'email', 'max:255'],
            'next_of_kin.*.occupation'       => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.address'          => ['nullable', 'string', 'max:255'],

            // Business — business customers only.
            'business'                        => ['required_if:customer_type,business', 'prohibited_unless:customer_type,business', 'array'],
            'business.business_name'          => ['required', 'string', 'max:255'],
            'business.trading_name'           => ['nullable', 'string', 'max:255'],
            'business.business_type'          => ['nullable', 'string', 'max:100'],
            'business.registration_number'    => ['nullable', 'string', 'max:100', 'unique:businesses,registration_number'],
            'business.registration_date'      => ['nullable', 'date'],
            'business.incorporation_date'     => ['nullable', 'date'],
            'business.nature_of_business'     => ['nullable', 'string', 'max:255'],
            'business.industry'               => ['nullable', 'string', 'max:255'],
            'business.tin'                    => ['nullable', 'string', 'max:50', 'unique:businesses,tin'],
            'business.vat_number'             => ['nullable', 'string', 'max:50', 'unique:businesses,vat_number'],
            'business.business_phone'         => ['nullable', 'string', 'max:20'],
            'business.business_email'         => ['nullable', 'email', 'max:255'],
            'business.website'                => ['nullable', 'string', 'max:255'],
            'business.annual_turnover'        => ['nullable', 'numeric', 'min:0'],
            'business.monthly_turnover'       => ['nullable', 'numeric', 'min:0'],
            'business.number_of_employees'    => ['nullable', 'integer', 'min:0'],
            'business.source_of_funds'        => ['nullable', 'string', 'max:255'],

            // Directors — business customers only.
            'directors'                      => ['required_if:customer_type,business', 'prohibited_unless:customer_type,business', 'array', 'min:1'],
            'directors.*.title'              => ['nullable', 'string', 'max:20'],
            'directors.*.first_name'         => ['required', 'string', 'max:255'],
            'directors.*.middle_name'        => ['nullable', 'string', 'max:255'],
            'directors.*.last_name'          => ['required', 'string', 'max:255'],
            'directors.*.position'           => ['required', 'string', 'max:100'],
            'directors.*.phone'              => ['required', 'string', 'max:20'],
            'directors.*.email'              => ['nullable', 'email', 'max:255'],
            'directors.*.gender'             => ['nullable', 'in:male,female'],
            'directors.*.dob'                => ['nullable', 'date', 'before:today'],
            'directors.*.nationality'        => ['nullable', 'string', 'max:100'],
            'directors.*.address'            => ['nullable', 'string'],
            'directors.*.occupation'         => ['nullable', 'string', 'max:255'],
            'directors.*.appointment_date'   => ['nullable', 'date'],
            'directors.*.resignation_date'   => ['nullable', 'date'],
            'directors.*.bvn'                => ['nullable', 'digits:11'],
            'directors.*.nin'                => ['nullable', 'digits:11'],
            'directors.*.tin'                => ['nullable', 'string', 'max:50'],
            'directors.*.id_type'            => ['nullable', 'string', 'max:50'],
            'directors.*.id_number'          => ['nullable', 'string', 'max:100'],
            'directors.*.passport_photo'     => ['nullable', 'string', 'max:500'],
            'directors.*.signature'          => ['nullable', 'string', 'max:500'],
            'directors.*.is_primary'         => ['sometimes', 'boolean'],

            // Signatories — business customers only. director_index optionally
            // links a signatory to a director submitted in the same request,
            // by its 0-based position in the `directors` array above.
            'signatories'                       => ['required_if:customer_type,business', 'prohibited_unless:customer_type,business', 'array', 'min:1'],
            'signatories.*.director_index'      => ['nullable', 'integer', 'min:0', 'lt:directors_count'],
            'signatories.*.title'               => ['nullable', 'string', 'max:20'],
            'signatories.*.first_name'          => ['required', 'string', 'max:255'],
            'signatories.*.middle_name'         => ['nullable', 'string', 'max:255'],
            'signatories.*.last_name'           => ['required', 'string', 'max:255'],
            'signatories.*.position'            => ['required', 'string', 'max:100'],
            'signatories.*.phone'               => ['required', 'string', 'max:20'],
            'signatories.*.email'               => ['nullable', 'email', 'max:255'],
            'signatories.*.signature'           => ['nullable', 'string', 'max:500'],
            'signatories.*.passport_photo'      => ['nullable', 'string', 'max:500'],
            'signatories.*.gender'              => ['nullable', 'in:male,female'],
            'signatories.*.dob'                 => ['nullable', 'date', 'before:today'],
            'signatories.*.bvn'                 => ['nullable', 'digits:11'],
            'signatories.*.nin'                 => ['nullable', 'digits:11'],
            'signatories.*.address'             => ['nullable', 'string'],
            'signatories.*.transaction_limit'   => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'directors_count' => count($this->input('directors', [])),
        ]);
    }

    protected function passedValidation(): void
    {
        $this->offsetUnset('directors_count');
    }
}
