<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Account;

use Illuminate\Foundation\Http\FormRequest;

// Deliberately no beneficiary_email/phone/bvn fields — those always mirror the parent
// customer's real KYC and are snapshotted server-side, never accepted from the client.
class CreateVirtualAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_product_id' => ['required', 'integer', 'exists:account_products,id'],
            'parent_account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'beneficiary_name'   => ['required', 'string', 'max:255'],
            'purpose'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'expires_at'         => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
