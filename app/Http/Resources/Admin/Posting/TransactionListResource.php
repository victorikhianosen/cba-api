<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Posting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'reference'             => $this->reference,
            'transaction_reference' => $this->transaction_reference,

            'account' => [
                'id'             => $this->account_id,
                'account_number' => $this->whenLoaded('account', fn () => $this->account?->account_number),
            ],

            'customer' => [
                'id'         => $this->customer_id,
                'full_name'  => $this->whenLoaded('customer', fn () => $this->customer
                    ? (trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name)
                    : null),
                'cif_number' => $this->whenLoaded('customer', fn () => $this->customer?->cif_number),
            ],

            // The other side of this leg — who sent/received this money.
            'counterparty' => [
                'type'           => $this->counterparty_type,
                'name'           => $this->counterparty_name,
                'account_number' => $this->counterparty_account_number,
            ],

            'transaction_type'  => $this->transaction_type,
            'business_service'  => $this->business_service,
            'category'          => $this->transaction_type,

            // Ledger type (debit/credit) merged with its plain-language direction
            // (inward/outward) into a single renderable string instead of two fields.
            'type' => $this->mergedTypeLabel(),

            'channel' => $this->channel,

            'amount'         => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after'  => $this->balance_after,
            'currency_code'  => $this->whenLoaded('currency', fn () => $this->currency?->code),

            'status' => $this->status,

            'date' => $this->posting_date ? \Carbon\Carbon::parse($this->posting_date)->format('Y-m-d H:i:s') : null,
        ];
    }

    private function mergedTypeLabel(): ?string
    {
        $type      = $this->type ? ucfirst($this->type) : null;
        $direction = $this->direction ? ucfirst($this->direction) : null;

        return match (true) {
            $type && $direction => "{$type} ({$direction})",
            (bool) $type        => $type,
            (bool) $direction   => $direction,
            default             => null,
        };
    }
}
