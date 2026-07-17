<?php

namespace App\Http\Requests\Admin\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class StorePermissionRequest extends FormRequest
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

                    if (Permission::where('name', $normalized)->where('guard_name', 'user')->exists()) {
                        $fail("A permission named '{$value}' already exists.");
                    }
                },
            ],
        ];
    }
}