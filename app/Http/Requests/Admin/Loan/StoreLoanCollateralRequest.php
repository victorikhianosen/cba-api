<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'                  => ['required', 'string', 'max:100'],
            'description'            => ['required', 'string'],
            'estimated_amount'      => ['nullable', 'numeric', 'min:0'],
            'proof_of_ownership'    => ['nullable', 'file', 'max:10240'],
        ];
    }
}
