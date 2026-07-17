<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'two_factor_token' => ['required', 'string'],
            'code'              => ['required', 'string', 'max_digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The authentication code must be 6 digits.',
        ];
    }
}
