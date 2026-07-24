<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\AccountTier;

use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountTierId = $this->route('id');

        return [
            'level'       => ['sometimes', 'integer', 'min:0', 'max:127', Rule::unique('account_tiers', 'level')->ignore($accountTierId)],
            'description' => ['sometimes', 'nullable', 'string'],

            'daily_transaction_limit'       => ['sometimes', 'nullable', 'integer', new StrictInteger, 'min:0'],
            'daily_withdrawal_limit'        => ['sometimes', 'nullable', 'integer', new StrictInteger, 'min:0'],
            'daily_transfer_limit'          => ['sometimes', 'nullable', 'integer', new StrictInteger, 'min:0'],
            'max_account_balance'           => ['sometimes', 'nullable', 'integer', new StrictInteger, 'min:0'],
            'max_single_transaction_amount' => ['sometimes', 'nullable', 'integer', new StrictInteger, 'min:0'],
            'max_daily_inflow'              => ['sometimes', 'nullable', 'integer', new StrictInteger, 'min:0'],
            'max_daily_outflow'             => ['sometimes', 'nullable', 'integer', new StrictInteger, 'min:0'],

            'require_bvn'                  => ['sometimes', 'boolean'],
            'require_nin'                  => ['sometimes', 'boolean'],
            'require_address_verification' => ['sometimes', 'boolean'],
            'require_face_verification'    => ['sometimes', 'boolean'],
        ];
    }
}
