<?php

namespace App\Http\Requests\Admin\AccountOfficer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $officerId = $this->route('id');

        return [
            'branch_id'  => ['sometimes', 'integer', 'exists:branches,id'],
            'user_id'    => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'string', 'max:255'],
            'email'      => ['sometimes', 'email', 'max:255', Rule::unique('account_officers', 'email')->ignore($officerId)],
            'phone'      => ['sometimes', 'string', 'max:30', Rule::unique('account_officers', 'phone')->ignore($officerId)],
            'gender'     => ['sometimes', 'nullable', 'in:male,female,other'],
            'address'    => ['sometimes', 'string', 'max:500'],
            'city'       => ['sometimes', 'string', 'max:100'],
            'state'      => ['sometimes', 'string', 'max:100'],
            'country'    => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
