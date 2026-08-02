<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Investment;

use Illuminate\Foundation\Http\FormRequest;

class PreviewInvestmentScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'investment_product_id'    => ['required', 'integer', 'exists:investment_products,id'],
            'principal_amount'         => ['required', 'numeric', 'min:0.01'],
            'tenure'                   => ['required', 'integer', 'min:1', 'max:365'],
            'interest_posting_period'  => ['required', 'string'],
        ];
    }
}
