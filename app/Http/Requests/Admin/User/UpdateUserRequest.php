<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'  => ['nullable', 'integer', 'exists:branches,id'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'username'   => ['nullable', 'string', 'max:255', 'unique:users,username,' . $this->route('id')],
            'email'      => ['nullable', 'email', 'max:255', 'unique:users,email,' . $this->route('id')],
            'gender'     => ['nullable', 'in:male,female,other'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'address'    => ['nullable', 'string', 'max:500'],
            'city'       => ['nullable', 'string', 'max:100'],
            'state'      => ['nullable', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'max:100'],
            'notes'      => ['nullable', 'string'],
            'enable_2fa' => ['boolean'],
        ];
    }
}