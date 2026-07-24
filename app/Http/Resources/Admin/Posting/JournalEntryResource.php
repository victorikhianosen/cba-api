<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Posting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'entry_group_id'    => $this->entry_group_id,
            'general_ledger_id' => $this->general_ledger_id,
            'entry_type'        => $this->entry_type,
            'amount'            => $this->amount,
            'balance_before'    => $this->balance_before,
            'balance_after'     => $this->balance_after,
            'currency_id'       => $this->currency_id,
            'value_date'        => $this->value_date,
            'posting_date'      => $this->posting_date,
            'narration'         => $this->narration,
        ];
    }
}
