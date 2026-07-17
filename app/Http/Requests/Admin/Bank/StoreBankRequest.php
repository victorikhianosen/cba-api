<?php

namespace App\Http\Requests\Admin\Bank;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name'  => ['required', 'string', 'max:255'],
            'bank_code'  => ['required', 'string', 'max:20', 'unique:banks,bank_code'],
            'short_code' => ['nullable', 'string', 'max:20', 'unique:banks,short_code'],
            'logo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
