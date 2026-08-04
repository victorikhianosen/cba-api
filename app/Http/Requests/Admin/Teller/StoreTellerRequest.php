<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Teller;

use Illuminate\Foundation\Http\FormRequest;

class StoreTellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'                          => ['required', 'integer', 'exists:branches,id'],
            'vault_id'                            => ['required', 'integer', 'exists:vaults,id'],
            'name'                                => ['required', 'string', 'max:255'],
            'currency_id'                         => ['required', 'integer', 'exists:currencies,id'],
            'opening_float_ceiling_amount'        => ['nullable', 'numeric', 'min:0'],
            'variance_tolerance_amount'           => ['nullable', 'numeric', 'min:0'],
            'requires_variance_approval'          => ['nullable', 'boolean'],
            'allows_teller_to_teller_transfer'    => ['nullable', 'boolean'],
        ];
    }
}
