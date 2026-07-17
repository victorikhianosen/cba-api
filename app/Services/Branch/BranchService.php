<?php

namespace App\Services\Branch;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BranchService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Branch::query()->latest()->paginate($perPage);
    }

    public function find(int $id): Branch
    {
        return Branch::findOrFail($id);
    }

    public function create(array $data): Branch
    {
        $data['code'] = $this->generateUniqueCode();

        $data['status'] = 'pending';
        return Branch::create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch->refresh();
    }

    public function updateStatus(Branch $branch, string $status): Branch
    {
        $branch->update([
            'status' => $status,
        ]);

        return $branch->refresh();
    }

    private function generateUniqueCode(): string
    {
        $next = Branch::count() + 1;

        do {
            $code = 'BR' . str_pad((string) $next, 5, '0', STR_PAD_LEFT); // BR00001
            $next++;
        } while (Branch::where('code', $code)->exists());

        return $code;
    }
}