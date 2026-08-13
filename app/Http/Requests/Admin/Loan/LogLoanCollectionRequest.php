<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogLoanCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_officer_id'   => ['nullable', 'integer', 'exists:account_officers,id'],
            'contact_method'           => ['required', Rule::in(['phone_call', 'sms', 'email', 'whatsapp', 'field_visit', 'letter', 'other'])],
            'outcome'                  => ['required', Rule::in([
                'no_answer', 'wrong_number', 'promise_to_pay', 'dispute', 'refused_to_pay',
                'paid', 'partial_payment', 'unreachable', 'other',
            ])],
            'promise_date'             => ['nullable', 'date', 'required_if:outcome,promise_to_pay'],
            'promise_amount'           => ['nullable', 'numeric', 'min:0.01', 'required_if:outcome,promise_to_pay'],
            'escalated'                => ['nullable', 'boolean'],
            'escalation_level'         => ['nullable', 'string', 'max:100'],
            'legal_action_initiated'    => ['nullable', 'boolean'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ];
    }
}
