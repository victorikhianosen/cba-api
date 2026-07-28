<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Validated against the `statuses` lookup table rather than a hardcoded enum, so
            // adding a new status is a data change (new row), not a code change. 'pending' is
            // excluded — it's only the initial state a customer is created in, not a state you
            // can transition back into once past it.
            'status'  => ['required', 'string', 'exists:statuses,code', Rule::notIn(['pending'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
