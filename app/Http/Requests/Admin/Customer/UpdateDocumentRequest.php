<?php

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'name'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'   => ['sometimes', 'in:pending,verified,approved,rejected'],
            'comments' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
