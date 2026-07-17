<?php

namespace App\Http\Resources\Admin\Bank;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'bank_name'  => $this->bank_name,
            'bank_code'  => $this->bank_code,
            'short_code' => $this->short_code,
            'logo'       => $this->logo ? Storage::disk('s3')->url($this->logo) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
