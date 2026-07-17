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
            'business_name'  => ['required_if:customer_type,business', 'nullable', 'string', 'max:255'],

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
        ];
    }
}
