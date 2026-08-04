<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Vault;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vaultId = (int) $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:45', Rule::unique('vaults', 'code')->ignore($vaultId)],
        ];
    }
}
