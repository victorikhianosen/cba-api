<?php

namespace App\Http\Requests\Admin\Communication;

use Illuminate\Foundation\Http\FormRequest;

class SendBulkEmailRequest extends FormRequest
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
            'subject'        => ['required', 'string', 'max:255'],
            'body'           => ['required', 'string'],
            'type'           => ['nullable', 'string', 'max:100'],
        ];
    }
}
