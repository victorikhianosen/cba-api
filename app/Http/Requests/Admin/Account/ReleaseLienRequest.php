<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Account;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseLienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
