<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Account;

use Illuminate\Foundation\Http\FormRequest;

// Internal test/demo scaffolding — stands in for a real NUBAN provider's inbound-payment
// webhook, which this phase does not integrate with yet.
class SimulateVirtualAccountCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'             => ['required', 'numeric', 'gt:0'],
            'narration'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'provider_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
