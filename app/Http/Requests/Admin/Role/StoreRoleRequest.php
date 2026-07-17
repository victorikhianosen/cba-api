<?php

namespace App\Http\Requests\Admin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $normalized = Str::snake(trim($value));

                    if (Role::where('name', $normalized)->where('guard_name', 'user')->exists()) {
                        $fail("A role named '{$value}' already exists.");
                    }
                },
            ],
        ];
    }
}