<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\InvestmentLetterTemplate;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Multiple templates can exist, but exactly one is ever "default" — that's the one
// InvestmentService::letterData() actually renders. Everything else here is plain CRUD
// around that single rule.
class InvestmentLetterTemplateService
{
    private const IMAGE_FIELDS = ['header_image', 'signatory_one_signature', 'signatory_two_signature'];

    public function __construct(
        private FileStorageService $files,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return InvestmentLetterTemplate::query()
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): InvestmentLetterTemplate
    {
        return InvestmentLetterTemplate::findOrFail($id);
    }

    public function getDefault(): ?InvestmentLetterTemplate
    {
        return InvestmentLetterTemplate::where('is_default', true)->first();
    }

    public function create(array $data): InvestmentLetterTemplate
    {
        foreach (self::IMAGE_FIELDS as $field) {
            if (($data[$field] ?? null) instanceof UploadedFile) {
                $data[$field] = $this->uploadImage($data[$field]);
            } else {
                unset($data[$field]);
            }
        }

        // The very first template ever created must be usable immediately — there's no
        // "set default" step to remember before letters start printing correctly. Beyond
        // that, whoever's creating it can also opt in via the "Set as default" checkbox.
        $wantsDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $template = InvestmentLetterTemplate::create($data);

        if ($wantsDefault || ! InvestmentLetterTemplate::where('id', '!=', $template->id)->exists()) {
            return $this->setDefault($template);
        }

        return $template;
    }

    public function update(InvestmentLetterTemplate $template, array $data): InvestmentLetterTemplate
    {
        foreach (self::IMAGE_FIELDS as $field) {
            if (($data[$field] ?? null) instanceof UploadedFile) {
                $this->files->delete($template->{$field});
                $data[$field] = $this->uploadImage($data[$field]);
            } else {
                unset($data[$field]);
            }
        }

        // Exactly one template must always be default. A checked box promotes this one
        // (demoting whichever else was default); an unchecked box on the *current*
        // default is rejected outright — you set a different template as default
        // instead of ever leaving none.
        $wantsDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : null;
        unset($data['is_default']);

        if ($wantsDefault === false && $template->is_default) {
            throw ValidationException::withMessages([
                'is_default' => ['At least one template must stay default — set a different template as default instead of unsetting this one.'],
            ]);
        }

        $template->update($data);
        $template->refresh();

        if ($wantsDefault === true && ! $template->is_default) {
            return $this->setDefault($template);
        }

        return $template;
    }

    public function delete(InvestmentLetterTemplate $template): void
    {
        if ($template->is_default) {
            throw ValidationException::withMessages([
                'template' => ['Set another template as default before deleting this one.'],
            ]);
        }

        foreach (self::IMAGE_FIELDS as $field) {
            $this->files->delete($template->{$field});
        }

        $template->delete();
    }

    private function uploadImage(UploadedFile $file): string
    {
        return $this->files->uploadImageWithBackgroundRemoved($file, 'investment-letter-templates');
    }

    public function setDefault(InvestmentLetterTemplate $template): InvestmentLetterTemplate
    {
        DB::transaction(function () use ($template) {
            InvestmentLetterTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });

        return $template->refresh();
    }
}
