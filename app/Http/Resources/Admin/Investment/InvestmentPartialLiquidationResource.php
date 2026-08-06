<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Investment;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentPartialLiquidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'reference' => $this->reference,

            'investment_id' => $this->investment_id,
            'investment'    => $this->whenLoaded('investment', fn () => $this->investmentSummary($this->investment)),

            'amount' => $this->amount,
            'reason' => $this->reason,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,

            'requested_by' => $this->requested_by,
            'requester'    => $this->whenLoaded('requestedBy', fn () => $this->userSummary($this->requestedBy)),
            'requested_at' => $this->created_at,

            'approved_by' => $this->approved_by,
            'approver'    => $this->whenLoaded('approvedBy', fn () => $this->userSummary($this->approvedBy)),
            'approved_at' => $this->approved_at,

            'rejected_by' => $this->rejected_by,
            'rejecter'    => $this->whenLoaded('rejectedBy', fn () => $this->userSummary($this->rejectedBy)),
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,

            'investment_transaction_id' => $this->investment_transaction_id,
            'transaction_reference'     => $this->transaction_reference,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function investmentSummary(?Investment $investment): ?array
    {
        if (! $investment) {
            return null;
        }

        return [
            'id'                 => $investment->id,
            'account_number'     => $investment->account_number,
            'current_principal'  => $investment->current_principal,
            'customer'           => $investment->relationLoaded('customer') && $investment->customer ? [
                'id'   => $investment->customer->id,
                'name' => trim(($investment->customer->first_name ?? '') . ' ' . ($investment->customer->last_name ?? '')),
            ] : null,
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
