<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\FundGl;

use App\Http\Resources\Admin\Posting\FinancialTransactionResource;
use App\Models\GeneralLedger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FundGlResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'reference'              => $this->reference,
            'transaction_reference'  => $this->transaction_reference,
            'idempotency_key'        => $this->idempotency_key,

            'source_id'   => $this->source_id,
            'source'      => $this->whenLoaded('source', fn () => $this->glSummary($this->source)),
            'destination_id' => $this->destination_id,
            'destination'    => $this->whenLoaded('destination', fn () => $this->glSummary($this->destination)),

            'amount'      => $this->amount,
            'currency_id' => $this->currency_id,

            'status'  => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'remarks' => $this->remarks,

            'created_by'  => $this->created_by,
            'creator'     => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->createdBy)),
            'reviewed_by' => $this->reviewed_by,
            'reviewer'    => $this->whenLoaded('reviewedBy', fn () => $this->userSummary($this->reviewedBy)),
            'reviewed_at' => $this->reviewed_at,
            'approved_by' => $this->approved_by,
            'approver'    => $this->whenLoaded('approvedBy', fn () => $this->userSummary($this->approvedBy)),
            'approved_at' => $this->approved_at,
            'rejected_by' => $this->rejected_by,
            'rejecter'    => $this->whenLoaded('rejectedBy', fn () => $this->userSummary($this->rejectedBy)),
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,

            'financial_transaction_id' => $this->financial_transaction_id,
            'financial_transaction'    => new FinancialTransactionResource($this->whenLoaded('financialTransaction')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function glSummary(?GeneralLedger $gl): ?array
    {
        if (! $gl) {
            return null;
        }

        return [
            'id'      => $gl->id,
            'name'    => $gl->name,
            'gl_code' => $gl->gl_code,
            'balance' => $gl->balance,
        ];
    }

    private function userSummary(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'username'   => $user->username,
        ];
    }
}
