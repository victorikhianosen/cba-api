<?php

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('id');

        return [
            'branch_id'          => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'account_officer_id' => ['sometimes', 'nullable', 'integer', 'exists:account_officers,id'],
            'customer_type'      => ['sometimes', 'in:individual,business,minor'],
            'guardian_id'        => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],

            'title'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'first_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'middle_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'phone'    => ['sometimes', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customerId)],
            'email'    => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customerId)],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('customers', 'username')->ignore($customerId)],

            'password'       => ['sometimes', 'nullable', 'string', 'min:6'],
            'panic_password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'pin'            => ['sometimes', 'nullable', 'digits:4'],

            'marital_status' => ['sometimes', 'nullable', 'in:single,married,divorced,widowed'],
            'gender'         => ['sometimes', 'nullable', 'in:male,female,other'],
            'dob'            => ['sometimes', 'nullable', 'date', 'before:today'],

            'occupation'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'working_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'referral_code'  => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('customers', 'referral_code')->ignore($customerId)],

            'bvn'        => ['sometimes', 'nullable', 'digits:11', Rule::unique('customers', 'bvn')->ignore($customerId)],
            'nin_number' => ['sometimes', 'nullable', 'digits:11', Rule::unique('customers', 'nin_number')->ignore($customerId)],
            'tin'        => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('customers', 'tin')->ignore($customerId)],

            'is_staff' => ['sometimes', 'boolean'],
            'pep'      => ['sometimes', 'boolean'],

            'enable_internet_bank'  => ['sometimes', 'boolean'],
            'enable_sms'            => ['sometimes', 'boolean'],
            'enable_email'          => ['sometimes', 'boolean'],
            'enable_reset_password' => ['sometimes', 'boolean'],
            'enable_panic_password' => ['sometimes', 'boolean'],

            'mother_maiden_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'spouse_name'        => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
