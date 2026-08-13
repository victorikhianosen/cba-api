<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class PreviewLoanTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topup_amount'               => ['required', 'numeric', 'min:0.01'],
            'new_duration'   => ['nullable', 'integer', 'min:1', 'max:24'],
            'new_interest_rate'          => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
