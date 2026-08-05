<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\AccountStatementTemplate;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountStatementTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $files = app(FileStorageService::class);

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'header_image' => $files->url($this->header_image),
            'is_default'   => $this->is_default,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
