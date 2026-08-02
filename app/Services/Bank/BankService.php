<?php

declare(strict_types=1);

namespace App\Services\Bank;

use App\Models\Bank;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class BankService
{
    public function __construct(
        private FileStorageService $files,
    ) {}

    public function list(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Bank::query()
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                    ->orWhere('bank_code', 'like', "%{$search}%")
                    ->orWhere('short_code', 'like', "%{$search}%");
            }))
            ->orderBy('bank_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Bank
    {
        return Bank::findOrFail($id);
    }

    public function create(array $data, ?UploadedFile $logo = null): Bank
    {
        if ($logo) {
            $data['logo'] = $this->files->upload($logo, 'bank-logos');
        }

        return Bank::create($data);
    }

    public function update(Bank $bank, array $data): Bank
    {
        $bank->update($data);

        return $bank->refresh();
    }

    public function delete(Bank $bank): void
    {
        $bank->delete();
    }

    public function uploadLogo(Bank $bank, UploadedFile $file): Bank
    {
        $before = $bank->logo;

        $path = $this->files->upload($file, 'bank-logos');

        $bank->update(['logo' => $path]);

        $this->files->delete($before);

        return $bank->refresh();
    }
}
