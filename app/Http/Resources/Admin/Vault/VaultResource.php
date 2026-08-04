<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Vault;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'code'              => $this->code,
            'status'            => $this->status,

            'branch_id'         => $this->branch_id,
            'branch_name'       => $this->whenLoaded('branch', fn () => $this->branch?->name),

            'currency_id'       => $this->currency_id,
            'currency_code'     => $this->whenLoaded('currency', fn () => $this->currency?->code),

            'general_ledger_id' => $this->general_ledger_id,
            'gl_code'           => $this->whenLoaded('generalLedger', fn () => $this->generalLedger?->gl_code),
            'balance'           => $this->whenLoaded('generalLedger', fn () => $this->generalLedger?->balance),

            'created_by'        => $this->created_by,
            'approved_by'       => $this->approved_by,
            'approved_at'       => $this->approved_at,

            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
