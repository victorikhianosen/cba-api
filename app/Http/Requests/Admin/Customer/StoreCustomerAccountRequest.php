<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_product_id' => ['required', 'integer', 'exists:account_products,id'],
            'account_type'       => ['required', Rule::in(['savings', 'current'])],
            'account_tier_id'    => ['sometimes', 'nullable', 'integer', 'exists:account_tiers,id'],
        ];
    }
}
