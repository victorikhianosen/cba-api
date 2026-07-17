<?php

namespace App\Http\Requests\Admin\GeneralLedger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGeneralLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge([
                'name' => strtolower(str_replace(' ', '_', trim($this->name))),
            ]);
        }

        if ($this->filled('type')) {
            $this->merge([
                'type' => strtolower(str_replace(' ', '_', trim($this->type))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name'                            => ['required', 'string', 'max:255', 'unique:general_ledgers,name'],
            'currency_id'                     => ['nullable', 'integer', 'exists:currencies,id'],
            'parent_id'                       => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'classification'                  => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'type'                            => ['required', 'string', 'max:255'],
            'manual_journal_entries_allowed'  => ['nullable', 'boolean'],
            'description'                     => ['nullable', 'string'],
        ];
    }
}
