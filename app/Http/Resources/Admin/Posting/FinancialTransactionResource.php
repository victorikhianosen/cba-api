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

            'module'            => $this->module,
            'transaction_type'  => $this->transaction_type,

            'customer_id' => $this->customer_id,
            'customer'    => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id'         => $this->customer->id,
                'cif_number' => $this->customer->cif_number,
                'full_name'  => trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name,
            ] : null),

            'amount'        => $this->amount,
            'fee'           => $this->fee,
            'vat'           => $this->vat,
            'currency_id'   => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
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
