<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Investment;

use Illuminate\Foundation\Http\FormRequest;

class RequestPartialLiquidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
