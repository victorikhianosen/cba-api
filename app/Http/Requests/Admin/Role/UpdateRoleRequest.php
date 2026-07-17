<?php

namespace App\Http\Requests\Admin\Role;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($roleId) {
                    $normalized = Str::snake(trim($value));

                    $exists = Role::where('name', $normalized)
                        ->where('guard_name', 'user')
                        ->where('id', '!=', $roleId)
                        ->exists();

                    if ($exists) {
                        $fail("A role named '{$value}' already exists.");
                    }
                },
            ],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}