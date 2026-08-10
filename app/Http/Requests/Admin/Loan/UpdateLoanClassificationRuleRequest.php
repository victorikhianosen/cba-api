<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanClassificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ruleId = $this->route('id');

        return [
            'name'              => ['sometimes', 'string', 'max:255', Rule::unique('loan_classification_rules', 'name')->ignore($ruleId)],
            'label'             => ['sometimes', 'string', 'max:255'],
            'min_days_overdue'  => ['sometimes', 'integer', 'min:0'],
            'max_days_overdue'  => ['nullable', 'integer', 'gte:min_days_overdue'],
            'sort_order'        => ['sometimes', 'integer', 'min:1'],
            'color'             => ['nullable', 'string', 'max:20'],
            'status'            => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
