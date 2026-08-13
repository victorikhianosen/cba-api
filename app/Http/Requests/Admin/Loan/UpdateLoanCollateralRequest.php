<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'                  => ['sometimes', 'string', 'max:100'],
            'description'            => ['sometimes', 'string'],
            'estimated_amount'      => ['nullable', 'numeric', 'min:0'],
            'proof_of_ownership'    => ['nullable', 'file', 'max:10240'],
        ];
    }
}
