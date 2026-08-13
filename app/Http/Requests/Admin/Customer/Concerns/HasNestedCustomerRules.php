<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer\Concerns;

use App\Enums\Customer\BusinessType;
use App\Enums\Customer\CustomerType;
use Illuminate\Validation\Rule;

// Field-level rules shared by StoreCustomerRequest and UpdateCustomerRequest for the nested
// customer blocks. Passing a $customerId scopes "*.id" fields to that customer (update mode);
// passing null omits them entirely (create mode never sends an id for a not-yet-existing row).
// "required_without:*.id" degrades to a plain "required" on create, since no *.id input exists then.
trait HasNestedCustomerRules
{
    // The payload always carries a single "address" object — the one to make current. Older
    // addresses are kept as history (status: previous) by CustomerService, never sent back up.
    // $customerId is unused here but kept for a consistent signature with the other *Rules() helpers.
    private function addressRules(?int $customerId): array
    {
        return [
            'address.type'      => ['nullable', 'string', 'max:50'],
            'address.address'   => ['required_with:address', 'nullable', 'string', 'max:255'],
            'address.lgs'       => ['nullable', 'string', 'max:100'],
            'address.city'      => ['nullable', 'string', 'max:100'],
            'address.state'     => ['nullable', 'string', 'max:100'],
            'address.country'   => ['nullable', 'string', 'max:100'],
            'address.landmark'  => ['nullable', 'string', 'max:255'],
            'address.longitude' => ['nullable', 'string', 'max:50'],
            'address.latitude'  => ['nullable', 'string', 'max:50'],
        ];
    }

    private function nextOfKinRules(?int $customerId): array
    {
        $rules = [
            'next_of_kin.*.title'        => ['nullable', 'string', 'max:20'],
            'next_of_kin.*.first_name'   => ['required_without:next_of_kin.*.id', 'nullable', 'string', 'max:255'],
            'next_of_kin.*.middle_name'  => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.last_name'    => ['required_without:next_of_kin.*.id', 'nullable', 'string', 'max:255'],
            'next_of_kin.*.relationship' => ['required_without:next_of_kin.*.id', 'nullable', 'string', 'max:100'],
            'next_of_kin.*.gender'       => ['nullable', 'in:male,female,other'],
            'next_of_kin.*.dob'          => ['nullable', 'date', 'before:today'],
            'next_of_kin.*.phone'        => ['required_without:next_of_kin.*.id', 'nullable', 'string', 'regex:/^\d+$/', 'max:15'],
            'next_of_kin.*.email'        => ['nullable', 'email', 'max:255'],
            'next_of_kin.*.occupation'   => ['nullable', 'string', 'max:255'],
            'next_of_kin.*.address'      => ['nullable', 'string', 'max:255'],
        ];

        if ($customerId) {
            $rules['next_of_kin.*.id'] = ['sometimes', 'nullable', 'integer', Rule::exists('next_of_kin', 'id')->where('customer_id', $customerId)];
        }

        return $rules;
    }

    private function businessRules(?int $customerId): array
    {
        $businessLikeTypes = implode(',', CustomerType::businessLike());

        return [
            'business.business_name'       => [$customerId ? 'sometimes' : "required_if:customer_type,{$businessLikeTypes}", 'nullable', 'string', 'max:255'],
            'business.trading_name'        => ['nullable', 'string', 'max:255'],
            'business.business_type'       => [$customerId ? 'sometimes' : "required_if:customer_type,{$businessLikeTypes}", 'nullable', Rule::in(BusinessType::values())],
            'business.registration_number' => ['nullable', 'string', 'max:100', Rule::unique('businesses', 'registration_number')->ignore($customerId, 'customer_id')],
            'business.registration_date'   => ['nullable', 'date'],
            'business.incorporation_date'  => ['nullable', 'date'],
            'business.nature_of_business'  => ['nullable', 'string', 'max:255'],
            'business.industry'            => ['nullable', 'string', 'max:255'],
            'business.tin'                 => ['nullable', 'string', 'max:50', Rule::unique('businesses', 'tin')->ignore($customerId, 'customer_id')],
            'business.vat_number'          => ['nullable', 'string', 'max:50', Rule::unique('businesses', 'vat_number')->ignore($customerId, 'customer_id')],
            'business.business_phone'      => ['nullable', 'string', 'regex:/^\d+$/', 'max:15'],
            'business.business_email'      => ['nullable', 'email', 'max:255'],
            'business.website'             => ['nullable', 'string', 'max:255'],
        ];
    }

    // Directors — an optional addition on any business-like customer (business, including
    // government via legal_form), not a distinct "corporate" type.
    private function directorRules(?int $customerId): array
    {
        $rules = [
            'directors.*.title'            => ['nullable', 'string', 'max:20'],
            'directors.*.first_name'       => ['required_without:directors.*.id', 'nullable', 'string', 'max:255'],
            'directors.*.middle_name'      => ['nullable', 'string', 'max:255'],
            'directors.*.last_name'        => ['required_without:directors.*.id', 'nullable', 'string', 'max:255'],
            'directors.*.position'         => ['required_without:directors.*.id', 'nullable', 'string', 'max:100'],
            'directors.*.phone'            => ['required_without:directors.*.id', 'nullable', 'string', 'regex:/^\d+$/', 'max:15'],
            'directors.*.email'            => ['nullable', 'email', 'max:255'],
            'directors.*.gender'           => ['nullable', 'in:male,female'],
            'directors.*.dob'              => ['nullable', 'date', 'before:today'],
            'directors.*.nationality'      => ['nullable', 'string', 'max:100'],
            'directors.*.address'          => ['nullable', 'string'],
            'directors.*.occupation'       => ['nullable', 'string', 'max:255'],
            'directors.*.appointment_date' => ['nullable', 'date'],
            'directors.*.resignation_date' => ['nullable', 'date'],
            'directors.*.bvn'              => ['nullable', 'digits:11'],
            'directors.*.nin'              => ['nullable', 'digits:11'],
            'directors.*.tin'              => ['nullable', 'string', 'max:50'],
            'directors.*.id_type'          => ['nullable', Rule::in(['nin', 'international_passport', 'drivers_license', 'voters_card'])],
            'directors.*.id_number'        => ['nullable', 'string', 'max:100'],
            'directors.*.shares_owned'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'directors.*.id_document'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'directors.*.passport_photo'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        if ($customerId) {
            $rules['directors.*.id'] = ['sometimes', 'nullable', 'integer', Rule::exists('directors', 'id')->where('customer_id', $customerId)];
        }

        return $rules;
    }

    private function signatoryRules(?int $customerId): array
    {
        $rules = [
            'signatories.*.title'          => ['nullable', 'string', 'max:20'],
            'signatories.*.first_name'     => ['required_without:signatories.*.id', 'nullable', 'string', 'max:255'],
            'signatories.*.middle_name'    => ['nullable', 'string', 'max:255'],
            'signatories.*.last_name'      => ['required_without:signatories.*.id', 'nullable', 'string', 'max:255'],
            'signatories.*.position'       => ['required_without:signatories.*.id', 'nullable', 'string', 'max:100'],
            'signatories.*.phone'          => ['required_without:signatories.*.id', 'nullable', 'string', 'regex:/^\d+$/', 'max:15'],
            'signatories.*.email'          => ['nullable', 'email', 'max:255'],
            'signatories.*.signature'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'signatories.*.passport_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'signatories.*.gender'         => ['nullable', 'in:male,female'],
            'signatories.*.dob'            => ['nullable', 'date', 'before:today'],
            'signatories.*.bvn'            => ['nullable', 'digits:11'],
            'signatories.*.nin'            => ['nullable', 'digits:11'],
            'signatories.*.address'        => ['nullable', 'string'],
        ];

        if ($customerId) {
            $rules['signatories.*.id'] = ['sometimes', 'nullable', 'integer', Rule::exists('signatories', 'id')->where('customer_id', $customerId)];
        }

        return $rules;
    }
}
