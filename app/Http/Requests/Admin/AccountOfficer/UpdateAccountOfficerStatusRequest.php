<?php

namespace App\Http\Requests\Admin\AccountOfficer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountOfficerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
