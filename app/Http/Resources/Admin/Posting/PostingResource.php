<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Posting;

use App\Models\Account;
use App\Models\GeneralLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
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
            'source'           => $this->partySummary($this->source_type, $this->source_id),
            'destination_type' => $this->destination_type,
            'destination_id'   => $this->destination_id,
            'destination'      => $this->partySummary($this->destination_type, $this->destination_id),

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
            'creator'      => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->createdBy)),
            'reviewed_by'  => $this->reviewed_by,
            'reviewer'     => $this->whenLoaded('reviewedBy', fn () => $this->userSummary($this->reviewedBy)),
            'reviewed_at'  => $this->reviewed_at,
            'approved_by'  => $this->approved_by,
            'approver'     => $this->whenLoaded('approvedBy', fn () => $this->userSummary($this->approvedBy)),
            'approved_at'  => $this->approved_at,
            'rejected_by'  => $this->rejected_by,
            'rejecter'     => $this->whenLoaded('rejectedBy', fn () => $this->userSummary($this->rejectedBy)),
            'rejected_at'  => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,

            'financial_transaction' => new FinancialTransactionResource($this->whenLoaded('financialTransaction')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    // Resolves a posting party (via the app's morph map — 'account'/'general_ledger') into a
    // small human-readable summary, so the frontend doesn't need a second lookup per party.
    private function partySummary(?string $type, ?int $id): ?array
    {
        if (! $type || ! $id) {
            return null;
        }

        $class = Relation::getMorphedModel($type);
        $model = $class ? $class::find($id) : null;

        if (! $model) {
            return null;
        }

        return match (true) {
            $model instanceof Account => [
                'type'              => 'account',
                'id'                => $model->id,
                'account_number'    => $model->account_number,
                'account_name'      => $model->account_name,
                'customer_id'       => $model->customer_id,
                'account_balance'   => $model->account_balance,
                'available_balance' => $model->available_balance,
            ],
            $model instanceof GeneralLedger => [
                'type'    => 'general_ledger',
                'id'      => $model->id,
                'name'    => $model->name,
                'gl_code' => $model->gl_code,
                'balance' => $model->balance,
            ],
            default => null,
        };
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
