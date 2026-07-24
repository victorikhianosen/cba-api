<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Posting;

use Illuminate\Foundation\Http\FormRequest;

class RejectPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
