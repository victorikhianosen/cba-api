<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Teller;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TellerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                                  => $this->id,
            'name'                                => $this->name,
            'code'                                => $this->code,
            'status'                              => $this->status,

            'branch_id'                           => $this->branch_id,
            'branch_name'                         => $this->whenLoaded('branch', fn () => $this->branch?->name),

            'vault_id'                            => $this->vault_id,
            'vault_name'                          => $this->whenLoaded('vault', fn () => $this->vault?->name),

            'currency_id'                         => $this->currency_id,
            'currency_code'                       => $this->whenLoaded('currency', fn () => $this->currency?->code),

            'general_ledger_id'                   => $this->general_ledger_id,
            'gl_code'                             => $this->whenLoaded('generalLedger', fn () => $this->generalLedger?->gl_code),
            'balance'                             => $this->whenLoaded('generalLedger', fn () => $this->generalLedger?->balance),

            'customer_cash_general_ledger_id'     => $this->customer_cash_general_ledger_id,
            'customer_cash_gl_code'               => $this->whenLoaded('customerCashGeneralLedger', fn () => $this->customerCashGeneralLedger?->gl_code),
            'customer_cash_balance'               => $this->whenLoaded('customerCashGeneralLedger', fn () => $this->customerCashGeneralLedger?->balance),

            'opening_float_ceiling_amount'        => $this->opening_float_ceiling_amount,
            'variance_tolerance_amount'           => $this->variance_tolerance_amount,
            'requires_variance_approval'          => $this->requires_variance_approval,
            'allows_teller_to_teller_transfer'    => $this->allows_teller_to_teller_transfer,

            'current_assignment' => $this->whenLoaded('currentAssignment', fn () => $this->currentAssignment ? [
                'id'          => $this->currentAssignment->id,
                'user_id'     => $this->currentAssignment->user_id,
                'user_name'   => trim(($this->currentAssignment->user?->first_name ?? '') . ' ' . ($this->currentAssignment->user?->last_name ?? '')) ?: $this->currentAssignment->user?->username,
                'assigned_at' => $this->currentAssignment->assigned_at,
            ] : null),

            'current_session' => $this->whenLoaded('currentSession', fn () => $this->currentSession ? [
                'id'                       => $this->currentSession->id,
                'status'                   => $this->currentSession->status,
                'cashier_id'               => $this->currentSession->cashier_id,
                'opening_float_amount'     => $this->currentSession->opening_float_amount,
                'expected_closing_balance' => $this->currentSession->expected_closing_balance,
                'opened_at'                => $this->currentSession->opened_at,
            ] : null),

            'assignment_history' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($assignment) => [
                'id'            => $assignment->id,
                'user_id'       => $assignment->user_id,
                'user_name'     => trim(($assignment->user?->first_name ?? '') . ' ' . ($assignment->user?->last_name ?? '')) ?: $assignment->user?->username,
                'assigned_by'   => $assignment->assigned_by,
                'assigned_at'   => $assignment->assigned_at,
                'unassigned_by' => $assignment->unassigned_by,
                'unassigned_at' => $assignment->unassigned_at,
            ])),

            'created_by'  => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,

            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
