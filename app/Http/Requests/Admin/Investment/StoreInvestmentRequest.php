<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'investment_product_id'    => ['required', 'integer', 'exists:investment_products,id'],
            'source_account_id'        => ['required', 'integer', 'exists:accounts,id'],
            'principal_amount'         => ['required', 'numeric', 'min:0.01'],
            'tenure'                   => ['required', 'integer', 'min:1', 'max:365'],
            'interest_posting_period'  => ['required', 'string'],
            // Only required when the chosen product offers auto-renewal — InvestmentService
            // checks that against the product itself, since this request can't see it.
            'rollover_type'            => ['nullable', Rule::in(['simple', 'compound'])],
        ];
    }
}
