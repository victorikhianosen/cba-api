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
            'id'                     => $this->id,
            'entry_group_id'         => $this->entry_group_id,
            'transaction_reference'  => $this->transaction_reference,
            'journal_reference'      => $this->journal_reference,
            'general_ledger_id'      => $this->general_ledger_id,
            'general_ledger'         => $this->whenLoaded('generalLedger', fn () => [
                'id'      => $this->generalLedger->id,
                'gl_code' => $this->generalLedger->gl_code,
                'name'    => $this->generalLedger->name,
            ]),
            'entry_type'        => $this->entry_type,
            'amount'            => $this->amount,
            'balance_before'    => $this->balance_before,
            'balance_after'     => $this->balance_after,
            'currency_id'       => $this->currency_id,
            'currency_code'     => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'value_date'        => $this->value_date,
            'posting_date'      => $this->posting_date,
            'narration'         => $this->narration,
            'created_at'        => $this->created_at,
        ];
    }
}
