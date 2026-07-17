<?php

namespace App\Http\Requests\Admin\Branch;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['nullable', 'email', 'max:255'],
            'phone'   => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'city'    => ['required', 'string', 'max:100'],
            'state'   => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }
}