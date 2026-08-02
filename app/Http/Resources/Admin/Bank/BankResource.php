<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Bank;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'bank_name'  => $this->bank_name,
            'bank_code'  => $this->bank_code,
            'short_code' => $this->short_code,
            'logo'       => app(FileStorageService::class)->url($this->logo),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
