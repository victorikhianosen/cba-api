<?php

namespace App\Http\Requests\Admin\Communication;

use Illuminate\Foundation\Http\FormRequest;

class SendBulkSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_ids'   => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'body'           => ['required', 'string', 'max:1000'],
            'type'           => ['nullable', 'string', 'max:100'],
        ];
    }
}
