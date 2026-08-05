<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\AccountStatementTemplate;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Multiple templates can exist, but exactly one is ever "default" — that's the one
// AccountService::statementData() actually renders. Only the header image is
// configurable (unlike investment letter templates, a statement has no signatories).
class AccountStatementTemplateService
{
    public function __construct(
        private FileStorageService $files,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AccountStatementTemplate::query()
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): AccountStatementTemplate
    {
        return AccountStatementTemplate::findOrFail($id);
    }

    public function getDefault(): ?AccountStatementTemplate
    {
        return AccountStatementTemplate::where('is_default', true)->first();
    }

    public function create(array $data): AccountStatementTemplate
    {
        if (($data['header_image'] ?? null) instanceof UploadedFile) {
            $data['header_image'] = $this->uploadImage($data['header_image']);
        } else {
            unset($data['header_image']);
        }

        // The very first template ever created must be usable immediately — there's no
        // "set default" step to remember before statements start printing correctly.
        // Beyond that, whoever's creating it can also opt in via the "Set as default" checkbox.
        $wantsDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $template = AccountStatementTemplate::create($data);

        if ($wantsDefault || ! AccountStatementTemplate::where('id', '!=', $template->id)->exists()) {
            return $this->setDefault($template);
        }

        return $template;
    }

    public function update(AccountStatementTemplate $template, array $data): AccountStatementTemplate
    {
        if (($data['header_image'] ?? null) instanceof UploadedFile) {
            $this->files->delete($template->header_image);
            $data['header_image'] = $this->uploadImage($data['header_image']);
        } else {
            unset($data['header_image']);
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

    public function delete(AccountStatementTemplate $template): void
    {
        if ($template->is_default) {
            throw ValidationException::withMessages([
                'template' => ['Set another template as default before deleting this one.'],
            ]);
        }

        $this->files->delete($template->header_image);
        $template->delete();
    }

    public function setDefault(AccountStatementTemplate $template): AccountStatementTemplate
    {
        DB::transaction(function () use ($template) {
            AccountStatementTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });

        return $template->refresh();
    }

    private function uploadImage(UploadedFile $file): string
    {
        return $this->files->uploadImageWithBackgroundRemoved($file, 'account-statement-templates');
    }
}
