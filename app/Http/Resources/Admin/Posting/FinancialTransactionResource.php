<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Posting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'transaction_reference'  => $this->transaction_reference,
            'posting_id'             => $this->posting_id,

            'amount'        => $this->amount,
            'fee'           => $this->fee,
            'vat'           => $this->vat,
            'currency_id'   => $this->currency_id,
            'exchange_rate' => $this->exchange_rate,

            'status'      => $this->status,
            'is_reversed' => $this->is_reversed,
            'narration'   => $this->narration,

            'processed_at' => $this->processed_at,
            'completed_at' => $this->completed_at,

            'journal_entries' => JournalEntryResource::collection($this->whenLoaded('journalEntries')),
            'transactions'    => TransactionResource::collection($this->whenLoaded('transactions')),

            'created_at' => $this->created_at,
        ];
    }
}
