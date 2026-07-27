<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer;

use App\Enums\Customer\CustomerType;
use App\Enums\Customer\LegalForm;
use App\Http\Requests\Admin\Customer\Concerns\HasNestedCustomerRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    use HasNestedCustomerRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = (int) $this->route('id');

        return [
            'branch_id'          => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'account_officer_id' => ['sometimes', 'nullable', 'integer', 'exists:account_officers,id'],
            'customer_type'      => ['sometimes', Rule::in(CustomerType::values())],
            'legal_form'         => ['sometimes', 'nullable', Rule::in(LegalForm::values())],
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
            'sex'            => ['sometimes', 'nullable', 'string', 'max:20'],
            'dob'            => ['sometimes', 'nullable', 'date', 'before:today'],

            'occupation'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'working_status' => ['sometimes', 'nullable', 'string', 'max:100'],

            'bvn'        => ['sometimes', 'nullable', 'digits:11', Rule::unique('customers', 'bvn')->ignore($customerId)],
            'nin_number' => ['sometimes', 'nullable', 'digits:11', Rule::unique('customers', 'nin_number')->ignore($customerId)],
            'tin'        => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('customers', 'tin')->ignore($customerId)],
            'rc_number'  => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('customers', 'rc_number')->ignore($customerId)],

            'is_staff' => ['sometimes', 'nullable', 'boolean'],

            'enable_reset_password' => ['sometimes', 'nullable', 'boolean'],
            'enable_panic_password' => ['sometimes', 'nullable', 'boolean'],

            'mother_maiden_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'spouse_name'        => ['sometimes', 'nullable', 'string', 'max:255'],

            'profile_picture' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'utility_bill'    => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'valid_id'        => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'business_logo'   => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // Address — one per customer (one-to-one), shared by every customer type.
            'address' => ['sometimes', 'nullable', 'array'],
            ...$this->addressRules($customerId),

            // Next of kin — no longer gated by customer_type; send it for whichever
            // customer it's relevant to.
            'next_of_kin' => ['sometimes', 'nullable', 'array'],
            ...$this->nextOfKinRules($customerId),

            // Business — single record, updated in place. No longer gated by customer_type.
            'business' => ['sometimes', 'nullable', 'array'],
            ...$this->businessRules($customerId),

            // Directors — include "id" to update an existing director, omit it to add a new one.
            // No longer gated by customer_type.
            'directors' => ['sometimes', 'nullable', 'array'],
            ...$this->directorRules($customerId),

            // Signatories — include "id" to update an existing signatory, omit it to add a new one.
            // No longer gated by customer_type.
            'signatories' => ['sometimes', 'nullable', 'array'],
            ...$this->signatoryRules($customerId),
        ];
    }

    // Booleans arrive as literal strings over multipart/form-data (needed for file uploads),
    // and Laravel's "boolean" rule only accepts 0/1/true/false — not the strings "true"/"false".
    // Normalize whatever was sent ("true", "1", "on", "yes", real bool, ...) before validating.
    private const BOOLEAN_FIELDS = [
        'is_staff', 'enable_reset_password', 'enable_panic_password',
    ];

    protected function prepareForValidation(): void
    {
        if ($this->filled('username')) {
            $this->merge([
                'username' => preg_replace('/\s+/', '', $this->username),
            ]);
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        if ($this->has('directors')) {
            $directors = $this->input('directors', []);

            foreach ($directors as $index => $director) {
                if (array_key_exists('is_primary', $director) && ! is_bool($director['is_primary'])) {
                    $directors[$index]['is_primary'] = filter_var($director['is_primary'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $this->merge(['directors' => $directors]);
        }
    }
}
