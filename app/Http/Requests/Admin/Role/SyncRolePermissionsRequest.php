<?php

namespace App\Http\Requests\Admin\Role;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Permission;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions'   => ['required', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Permission::where('name', $value)->where('guard_name', 'user')->exists()) {
                        $fail("The permission '{$value}' does not exist.");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.*.distinct' => 'The same permission cannot be assigned twice in one request.',
        ];
    }
}