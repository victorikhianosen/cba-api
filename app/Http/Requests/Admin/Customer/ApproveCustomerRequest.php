<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer;

use App\Enums\Customer\CustomerType;
use App\Models\AccountProduct;
use App\Models\Customer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Admin must pick the account product to assign on approval; tier is optional.
            'account_product_id' => ['required', 'integer', 'exists:account_products,id'],
            'account_tier_id'    => ['sometimes', 'nullable', 'integer', 'exists:account_tiers,id'],
        ];
    }

    // Individual/minor customers must be assigned a savings product; every business-like
    // type (business, government, corporate) must be assigned a current product.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('account_product_id')) {
                return;
            }

            $customer       = Customer::find((int) $this->route('id'));
            $accountProduct = AccountProduct::find($this->input('account_product_id'));

            if (! $customer || ! $accountProduct) {
                return;
            }

            $expectedType = in_array($customer->customer_type, CustomerType::businessLike(), true)
                ? 'current'
                : 'savings';

            if ($accountProduct->product_type !== $expectedType) {
                $validator->errors()->add(
                    'account_product_id',
                    "This customer requires a '{$expectedType}' account product, but '{$accountProduct->name}' is a '{$accountProduct->product_type}' product.",
                );
            }
        });
    }
}
