<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\InvestmentProduct;

use Illuminate\Foundation\Http\FormRequest;

class UploadInvestmentProductIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'icon' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
