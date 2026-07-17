<?php

namespace App\Http\Requests\Admin\User;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

class AssignUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles'   => ['required', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Role::where('name', $value)->where('guard_name', 'user')->exists()) {
                        $fail("The role '{$value}' does not exist.");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.*.distinct' => 'The same role cannot be specified twice in one request.',
        ];
    }
}