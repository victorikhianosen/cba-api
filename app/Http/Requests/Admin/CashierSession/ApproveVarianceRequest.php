<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\CashierSession;

use Illuminate\Foundation\Http\FormRequest;

class ApproveVarianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
