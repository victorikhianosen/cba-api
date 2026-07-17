<?php

namespace App\Http\Resources\Admin\GeneralLedger;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                              => $this->id,
            'name'                            => $this->name,
            'gl_code'                         => $this->gl_code,
            'balance'                         => $this->balance,
            'currency_id'                     => $this->currency_id,
            'currency_code'                   => $this->currency_code,
            'parent_id'                       => $this->parent_id,
            'hierarchy'                       => $this->hierarchy,
            'classification'                  => $this->classification,
            'type'                            => $this->type,
            'manual_journal_entries_allowed'  => $this->manual_journal_entries_allowed,
            'status'                          => $this->status,
            'description'                     => $this->description,
            'created_at'                      => $this->created_at,
            'updated_at'                      => $this->updated_at,
        ];
    }
}
