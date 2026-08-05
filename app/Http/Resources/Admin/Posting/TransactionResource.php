<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Posting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'entry_group_id'        => $this->entry_group_id,
            'reference'             => $this->reference,
            'transaction_reference' => $this->transaction_reference,
            'account_id'      => $this->account_id,
            'account'         => $this->whenLoaded('account', fn () => $this->account ? [
                'id'             => $this->account->id,
                'account_number' => $this->account->account_number,
            ] : null),
            'customer_id'     => $this->customer_id,
            'customer'        => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id'         => $this->customer->id,
                'cif_number' => $this->customer->cif_number,
                'full_name'  => trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name,
            ] : null),
            'type'            => $this->type,
            // Plain-language reading of 'type' — credit means money came into this
            // account (inward), debit means it left (outward).
            'direction'       => $this->direction,

            // Free-text service taxonomy — new products (airtime, data, cable, electricity,
            // betting, card, interbank, intrabank, etc.) are just new values here, no schema
            // or code change required.
            'module'               => $this->module,
            'transaction_type'     => $this->transaction_type,
            'transaction_subtype'  => $this->transaction_subtype,
            'business_service'     => $this->business_service,
            'channel'              => $this->channel,
            'service_provider'     => $this->service_provider,
            'provider_reference'   => $this->provider_reference,

            // The other side of this leg — who sent/received this money — denormalized
            // so it's visible without joining the posting or the sibling leg.
            'counterparty' => [
                'type'            => $this->counterparty_type,
                'id'              => $this->counterparty_id,
                'name'            => $this->counterparty_name,
                'account_number'  => $this->counterparty_account_number,
                'bank_code'       => $this->counterparty_bank_code,
                'bank_name'       => $this->counterparty_bank_name,
            ],

            'amount'          => $this->amount,
            'balance_before'  => $this->balance_before,
            'balance_after'   => $this->balance_after,
            'currency_id'     => $this->currency_id,
            'currency_code'   => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'value_date'      => $this->value_date,
            'posting_date'    => $this->posting_date,
            'status'          => $this->status,
            'narration'       => $this->narration,
            'metadata'        => $this->metadata,
            'created_by'      => $this->created_by,
            'creator'         => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id'         => $this->createdBy->id,
                'first_name' => $this->createdBy->first_name,
                'last_name'  => $this->createdBy->last_name,
                'username'   => $this->createdBy->username,
            ] : null),
            'created_at'      => $this->created_at,
        ];
    }
}
