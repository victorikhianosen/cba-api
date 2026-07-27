<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_ids'         => ['required', 'array', 'min:1'],
            'customer_ids.*'       => ['integer'],
            'branch_id'            => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'account_officer_id'   => ['sometimes', 'nullable', 'integer', 'exists:account_officers,id'],
        ];
    }
}
