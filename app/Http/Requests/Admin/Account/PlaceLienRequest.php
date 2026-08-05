<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Account;

use Illuminate\Foundation\Http\FormRequest;

class PlaceLienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'     => ['required', 'numeric', 'gt:0'],
            'reason'     => ['nullable', 'string', 'max:255'],
            'remarks'    => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
