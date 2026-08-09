<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Account;

use App\Enums\Account\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVirtualAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(fn ($case) => $case->value, AccountStatus::cases()))],
        ];
    }
}
