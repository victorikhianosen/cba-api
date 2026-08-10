<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanClassificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255', 'unique:loan_classification_rules,name'],
            'label'             => ['required', 'string', 'max:255'],
            'min_days_overdue'  => ['required', 'integer', 'min:0'],
            'max_days_overdue'  => ['nullable', 'integer', 'gte:min_days_overdue'],
            'sort_order'        => ['required', 'integer', 'min:1'],
            'color'             => ['nullable', 'string', 'max:20'],
            'status'            => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
