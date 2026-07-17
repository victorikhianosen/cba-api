<?php

namespace App\Http\Requests\Admin\AccountOfficer;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'  => ['required', 'integer', 'exists:branches,id'],
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:account_officers,email'],
            'phone'      => ['required', 'string', 'max:30', 'unique:account_officers,phone'],
            'gender'     => ['nullable', 'in:male,female,other'],
            'address'    => ['required', 'string', 'max:500'],
            'city'       => ['required', 'string', 'max:100'],
            'state'      => ['required', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'max:100'],
        ];
    }
}
