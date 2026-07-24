<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Account;

use App\Enums\Account\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(AccountStatus::values())],
        ];
    }
}
