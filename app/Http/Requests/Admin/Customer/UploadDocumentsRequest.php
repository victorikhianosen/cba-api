<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Every uploaded file becomes its own document — the field name it's sent under becomes that document's type/title, so rules are built dynamically per request.
    public function rules(): array
    {
        return collect($this->allFiles())
            ->mapWithKeys(fn ($file, $key) => [$key => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']])
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->allFiles())) {
                $validator->errors()->add('files', 'At least one file must be uploaded.');
            }
        });
    }
}
