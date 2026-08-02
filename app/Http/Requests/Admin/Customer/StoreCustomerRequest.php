<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer;

use App\Enums\Customer\CustomerType;
use App\Enums\Customer\LegalForm;
use App\Http\Requests\Admin\Customer\Concerns\HasNestedCustomerRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    use HasNestedCustomerRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessLikeTypes = implode(',', CustomerType::businessLike());
        $isBusinessLike     = in_array($this->input('customer_type'), CustomerType::businessLike(), true);

        // A business-like customer whose business_type is "not_registered" is captured just like
        // an individual — no signatories/directors required, only the normal personal fields + business block.
        $isUnregisteredBusiness = in_array($this->input('customer_type'), CustomerType::businessLike(), true)
            && $this->input('business.business_type') === 'not_registered';

        return [
            'branch_id'           => ['nullable', 'integer', 'exists:branches,id'],
            'account_officer_id'  => ['nullable', 'integer', 'exists:account_officers,id'],
            'customer_type'       => ['sometimes', Rule::in(CustomerType::values())],
            'legal_form'          => ['sometimes', 'nullable', Rule::in(LegalForm::values())],
            'guardian_id'         => ['nullable', 'integer', 'exists:customers,id'],

            'title'          => ['nullable', 'string', 'max:20'],
            'first_name'     => ["required_if:customer_type,individual,{$businessLikeTypes}", 'nullable', 'string', 'max:255'],
            'middle_name'    => ['nullable', 'string', 'max:255'],
            'last_name'      => ["required_if:customer_type,individual,{$businessLikeTypes}", 'nullable', 'string', 'max:255'],

            'phone'    => ['required', 'string', 'regex:/^\d+$/', 'max:15', 'unique:customers,phone'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:customers,username'],

            'password'       => ['nullable', 'string', 'min:6'],
            'pin'            => ['nullable', 'digits:4'],

            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'gender'         => ['nullable', 'in:male,female,other'],
            'dob'            => ['nullable', 'date', 'before:today'],

            'occupation'      => ['nullable', 'string', 'max:255'],
            'working_status'  => ['nullable', 'string', 'max:100'],

            'bvn'         => ['nullable', 'digits:11', 'unique:customers,bvn'],
            'nin_number'  => ['nullable', 'digits:11', 'unique:customers,nin_number'],
            'tin'         => ['nullable', 'string', 'max:50', 'unique:customers,tin'],
            'rc_number'   => ['nullable', 'string', 'max:50', 'unique:customers,rc_number'],

            'is_staff' => ['sometimes', 'nullable', 'boolean'],

            'mother_maiden_name' => ['nullable', 'string', 'max:255'],
            'spouse_name'        => ['nullable', 'string', 'max:255'],

            'device_token' => ['nullable', 'string', 'max:255'],
            'device_id'    => ['nullable', 'string', 'max:255'],

            // Arbitrary custom fields the frontend lets a user add on the fly: each entry is
            // a label/value pair, with no fixed schema on our side.
            'metadata'          => ['sometimes', 'nullable', 'array'],
            'metadata.*.label'  => ['required_with:metadata', 'string', 'max:255'],
            'metadata.*.value'  => ['nullable', 'string', 'max:1000'],

            'profile_picture' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'utility_bill'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'valid_id'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'business_logo'   => ["prohibited_unless:customer_type,{$businessLikeTypes}", 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // Address — one per customer (one-to-one), shared by every customer type.
            'address' => ['sometimes', 'nullable', 'array'],
            ...$this->addressRules(null),

            // Next of kin — individual/minor customers only.
            'next_of_kin' => ['sometimes', 'nullable', 'array', "prohibited_if:customer_type,{$businessLikeTypes}"],
            ...$this->nextOfKinRules(null),

            // Business — business-like customers only.
            'business' => ["required_if:customer_type,{$businessLikeTypes}", "prohibited_unless:customer_type,{$businessLikeTypes}", 'array'],
            ...$this->businessRules(null),

            // Directors — an optional addition available to any business-like customer (not
            // a distinct "corporate" type), and not offered at all when the business is
            // not_registered. Whether a business "is corporate" is just "does it have
            // director rows", not a separate category.
            'directors' => [
                $isBusinessLike && ! $isUnregisteredBusiness ? 'sometimes' : 'prohibited',
                'nullable',
                'array',
            ],
            ...$this->directorRules(null),

            // Signatories — required for registered business-like customers; not_registered
            // businesses skip this entirely and are captured just like an individual.
            'signatories' => [
                $isUnregisteredBusiness ? 'prohibited' : "required_if:customer_type,{$businessLikeTypes}",
                "prohibited_unless:customer_type,{$businessLikeTypes}",
                'array',
                'min:1',
            ],
            ...$this->signatoryRules(null),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'directors_count' => count($this->input('directors', [])),
        ]);

        if ($this->filled('username')) {
            $this->merge([
                'username' => preg_replace('/\s+/', '', $this->username),
            ]);
        }

        // Booleans arrive as literal strings over multipart/form-data (needed for file uploads),
        // and Laravel's "boolean" rule only accepts 0/1/true/false — not the strings "true"/"false".
        if ($this->has('is_staff') && ! is_bool($this->input('is_staff'))) {
            $this->merge(['is_staff' => filter_var($this->input('is_staff'), FILTER_VALIDATE_BOOLEAN)]);
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

    protected function passedValidation(): void
    {
        $this->offsetUnset('directors_count');
    }
}
