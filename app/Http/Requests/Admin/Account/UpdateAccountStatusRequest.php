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
        // 'pending' is excluded — it's only the initial state an account is opened in, not a
        // state you can transition back into once past it.
        $allowedStatuses = array_filter(
            AccountStatus::values(),
            fn (string $status) => $status !== AccountStatus::PENDING->value,
        );

        return [
            'status' => ['required', Rule::in($allowedStatuses)],
        ];
    }
}
