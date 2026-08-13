<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class RealizeLoanCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
