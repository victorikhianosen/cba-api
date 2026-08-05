<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\InvestmentLetterTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentLetterTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                     => ['required', 'string', 'max:255'],
            'header_image'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'signatory_one_name'       => ['nullable', 'string', 'max:255'],
            'signatory_one_signature'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'signatory_two_name'       => ['nullable', 'string', 'max:255'],
            'signatory_two_signature'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_default'               => ['nullable', 'boolean'],
        ];
    }
}
