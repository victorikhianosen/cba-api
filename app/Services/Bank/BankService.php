<?php

namespace App\Services\Bank;

use App\Models\Bank;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BankService
{
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
            $data['logo'] = $logo->store('bank-logos', 's3');
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

        $path = $file->store('bank-logos', 's3');

        $bank->update(['logo' => $path]);

        if ($before) {
            Storage::disk('s3')->delete($before);
        }

        return $bank->refresh();
    }
}
