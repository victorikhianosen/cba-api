<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\CashierSession;

use App\Models\Account;
use App\Models\GeneralLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashierTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $counterparty = $this->counterparty();
        // whenLoaded() returns a MissingValue sentinel (not null) when the relation isn't
        // eager-loaded — relationLoaded()+direct access gives a real Posting|null instead,
        // so the null-safe calls below don't blow up on an unloaded relation.
        $posting = $this->relationLoaded('posting') ? $this->posting : null;

        return [
            'id'                  => $this->id,
            'cashier_session_id'  => $this->cashier_session_id,
            'posting_id'          => $this->posting_id,
            'type'                => $this->type,
            'status'              => $this->status,
            'counterparty_type'   => $this->counterparty_type,
            'counterparty_id'     => $this->counterparty_id,
            'counterparty_label'  => $this->counterparty_type === 'account'
                ? trim(($counterparty?->account_number ?? '') . ' — ' . ($counterparty?->account_name ?? ''))
                : ($counterparty?->name ?? null),
            'counterparty_name'   => $this->counterparty_type === 'account' ? $counterparty?->account_name : ($counterparty?->name ?? null),
            'counterparty_account_number' => $this->counterparty_type === 'account' ? $counterparty?->account_number : null,
            'amount'              => $this->amount,
            'running_balance'     => $this->running_balance,
            'narration'           => $this->narration,
            'created_by'          => $this->created_by,
            'requested_by'        => $this->requested_by,
            'requested_by_name'   => $this->whenLoaded('requestedBy', fn () => trim(($this->requestedBy?->first_name ?? '') . ' ' . ($this->requestedBy?->last_name ?? '')) ?: $this->requestedBy?->username),

            'approved_by_name'    => $this->when($posting !== null, function () use ($posting) {
                $approver = $posting->relationLoaded('approvedBy') ? $posting->approvedBy : null;

                return $approver ? (trim(($approver->first_name ?? '') . ' ' . ($approver->last_name ?? '')) ?: $approver->username) : null;
            }),
            'rejection_reason'    => $this->when($posting !== null, fn () => $posting->rejection_reason),

            // Source/destination of the underlying posting — who's actually being debited
            // and credited — so an approver can see exactly what will move before approving.
            'source'              => $this->when($posting !== null, fn () => self::resolveParty($posting->source_type, $posting->source_id)),
            'destination'         => $this->when($posting !== null, fn () => self::resolveParty($posting->destination_type, $posting->destination_id)),

            'teller_id'           => $this->whenLoaded('cashierSession', fn () => $this->cashierSession?->teller_id),
            'teller_name'         => $this->whenLoaded('cashierSession', fn () => $this->cashierSession?->teller?->name),
            'teller_code'         => $this->whenLoaded('cashierSession', fn () => $this->cashierSession?->teller?->code),

            'created_at'          => $this->created_at,
        ];
    }

    private static function resolveParty(?string $type, ?int $id): ?array
    {
        if (! $type || ! $id) {
            return null;
        }

        if ($type === 'account') {
            $account = Account::with('currency')->find($id);

            if (! $account) {
                return null;
            }

            return [
                'type'           => 'account',
                'label'          => trim("{$account->account_number} — {$account->account_name}"),
                'name'           => $account->account_name,
                'account_number' => $account->account_number,
                'balance'        => $account->available_balance,
                'currency_code'  => $account->currency?->code,
            ];
        }

        if ($type === 'general_ledger') {
            $gl = GeneralLedger::with('currency')->find($id);

            if (! $gl) {
                return null;
            }

            return [
                'type'           => 'general_ledger',
                'label'          => $gl->name,
                'name'           => $gl->name,
                'account_number' => null,
                'balance'        => $gl->balance,
                'currency_code'  => $gl->currency?->code,
            ];
        }

        return null;
    }
}
