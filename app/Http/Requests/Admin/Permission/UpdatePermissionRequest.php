<?php

namespace App\Http\Requests\Admin\Permission;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($permissionId) {
                    $normalized = Str::snake(trim($value));

                    $exists = Permission::where('name', $normalized)
                        ->where('guard_name', 'user')
                        ->where('id', '!=', $permissionId)
                        ->exists();

                    if ($exists) {
                        $fail("A permission named '{$value}' already exists.");
                    }
                },
            ],
        ];
    }
}