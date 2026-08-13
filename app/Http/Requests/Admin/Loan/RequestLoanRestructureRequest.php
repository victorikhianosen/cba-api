<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class RequestLoanRestructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason'                    => ['required', 'string', 'max:1000'],
            'new_duration'   => ['nullable', 'integer', 'min:1', 'max:24'],
            'new_interest_rate'          => ['nullable', 'numeric', 'min:0'],
            'capitalized_interest'       => ['nullable', 'numeric', 'min:0'],
            'capitalized_fees'           => ['nullable', 'numeric', 'min:0'],
            'waived_amount'              => ['nullable', 'numeric', 'min:0'],
            'restructure_fee'            => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
