<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\InvestmentLetterTemplate;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentLetterTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $files = app(FileStorageService::class);

        return [
            'id'                       => $this->id,
            'name'                     => $this->name,
            'header_image'             => $files->url($this->header_image),
            'signatory_one_name'       => $this->signatory_one_name,
            'signatory_one_signature'  => $files->url($this->signatory_one_signature),
            'signatory_two_name'       => $this->signatory_two_name,
            'signatory_two_signature'  => $files->url($this->signatory_two_signature),
            'is_default'               => $this->is_default,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
