<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Teller;

use Illuminate\Foundation\Http\FormRequest;

class AssignTellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
