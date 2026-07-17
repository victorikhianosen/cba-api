<?php

namespace App\Http\Requests\Admin\Bank;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name'  => ['nullable', 'string', 'max:255'],
            'bank_code'  => ['nullable', 'string', 'max:20', 'unique:banks,bank_code,' . $this->route('id')],
            'short_code' => ['nullable', 'string', 'max:20', 'unique:banks,short_code,' . $this->route('id')],
        ];
    }
}
