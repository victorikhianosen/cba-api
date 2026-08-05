<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\AccountStatementTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountStatementTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_default'   => ['nullable', 'boolean'],
        ];
    }
}
