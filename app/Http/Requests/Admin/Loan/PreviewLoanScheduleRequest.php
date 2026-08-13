<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;

class PreviewLoanScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_product_id'       => ['required', 'integer', 'exists:loan_products,id'],
            'principal_amount'      => ['required', 'numeric', 'min:0.01'],
            'duration'  => ['required', 'integer', 'min:1'],
        ];
    }
}
