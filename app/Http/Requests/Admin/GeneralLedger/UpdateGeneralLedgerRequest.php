<?php

namespace App\Http\Requests\Admin\GeneralLedger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralLedgerRequest extends FormRequest
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
        $generalLedgerId = $this->route('id');

        return [
            'name'                            => ['nullable', 'string', 'max:255', Rule::unique('general_ledgers', 'name')->ignore($generalLedgerId)],
            'parent_id'                       => ['nullable', 'integer', 'exists:general_ledgers,id'],
            'classification'                  => ['nullable', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'type'                            => ['nullable', 'string', 'max:255'],
            'manual_journal_entries_allowed'  => ['nullable', 'boolean'],
            'description'                     => ['nullable', 'string'],
        ];
    }
}
