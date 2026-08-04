<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Teller;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                                => ['sometimes', 'string', 'max:255'],
            'opening_float_ceiling_amount'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variance_tolerance_amount'           => ['sometimes', 'numeric', 'min:0'],
            'requires_variance_approval'          => ['sometimes', 'boolean'],
            'allows_teller_to_teller_transfer'    => ['sometimes', 'boolean'],
        ];
    }
}
