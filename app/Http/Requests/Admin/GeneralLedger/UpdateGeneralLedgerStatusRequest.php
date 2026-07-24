<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\GeneralLedger;

use App\Enums\GeneralLedger\GeneralLedgerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralLedgerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                GeneralLedgerStatus::ACTIVE->value,
                GeneralLedgerStatus::INACTIVE->value,
                GeneralLedgerStatus::REJECTED->value,
            ])],
        ];
    }
}
