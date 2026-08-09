<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Setting;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $masked = $this->sensitive || $this->encrypted;

        // An 'image' setting stores a storage path in `value` (same convention as
        // Bank::logo) — resolve it to a public URL here, same as BankResource does,
        // rather than exposing the raw path to the frontend.
        $value = match (true) {
            $masked               => '••••••',
            $this->type === 'image' => app(FileStorageService::class)->url($this->value),
            default                => $this->value,
        };

        return [
            'key'         => $this->key,
            'category'    => $this->category,
            'label'       => $this->label,
            'description' => $this->description,
            'type'        => $this->type,
            'value'       => $value,
            'editable'    => $this->editable,
            'status'      => $this->status,
            'updated_by'  => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->username),
            'updated_at'  => $this->updated_at,
        ];
    }
}
