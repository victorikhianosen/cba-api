<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Posting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'reference'              => $this->reference,
            'transaction_reference'  => $this->transaction_reference,
            'idempotency_key'        => $this->idempotency_key,

            'module'              => $this->module,
            'transaction_type'    => $this->transaction_type,
            'transaction_subtype' => $this->transaction_subtype,
            'business_service'    => $this->business_service,
            'channel'             => $this->channel,

            'source_type'      => $this->source_type,
            'source_id'        => $this->source_id,
            'destination_type' => $this->destination_type,
            'destination_id'   => $this->destination_id,

            'amount'        => $this->amount,
            'currency_id'   => $this->currency_id,
            'exchange_rate' => $this->exchange_rate,
            'fee'           => $this->fee,
            'vat'           => $this->vat,
            'tax'           => $this->tax,
            'total_amount'  => $this->total_amount,

            'priority'         => $this->priority,
            'status'           => $this->status,
            'approval_level'   => $this->approval_level,

            'remarks' => $this->remarks,

            'created_by'   => $this->created_by,
            'reviewed_by'  => $this->reviewed_by,
            'reviewed_at'  => $this->reviewed_at,
            'approved_by'  => $this->approved_by,
            'approved_at'  => $this->approved_at,
            'rejected_by'  => $this->rejected_by,
            'rejected_at'  => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,

            'financial_transaction' => new FinancialTransactionResource($this->whenLoaded('financialTransaction')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
