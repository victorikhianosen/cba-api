<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Investment;

use App\Http\Resources\Admin\InvestmentProduct\InvestmentProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'account_number'         => $this->account_number,
            'customer_id'            => $this->customer_id,
            'customer'               => $this->whenLoaded('customer', fn () => [
                'id'            => $this->customer->id,
                'cif_number'    => $this->customer->cif_number,
                'full_name'     => trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name,
                'email'         => $this->customer->email,
                'phone'         => $this->customer->phone,
            ]),
            'investment_product_id'  => $this->investment_product_id,
            'investment_product'     => new InvestmentProductResource($this->whenLoaded('investmentProduct')),
            'source_account_id'      => $this->source_account_id,
            'source_account'         => $this->whenLoaded('sourceAccount', fn () => [
                'id'             => $this->sourceAccount->id,
                'account_number' => $this->sourceAccount->account_number,
                'account_name'   => $this->sourceAccount->account_name,
            ]),
            'currency_id'            => $this->currency_id,

            'principal_amount'                => $this->principal_amount,
            'interest_rate'                    => $this->interest_rate,
            'interest_posting_period'          => $this->interest_posting_period,
            'tenure'                            => $this->tenure,
            'early_liquidation_lock_days'       => $this->early_liquidation_lock_days,
            'early_liquidation_penalty_rate'    => $this->early_liquidation_penalty_rate,
            'withholding_tax_rate'              => $this->withholding_tax_rate,
            'auto_renew'                        => $this->auto_renew,
            'rollover_type'                     => $this->rollover_type,

            'start_date'    => $this->start_date,
            'maturity_date' => $this->maturity_date,
            'status'        => $this->status,

            // Running financial position — materialized, not recomputed from transaction history.
            'current_principal'               => $this->current_principal,
            'total_top_up_amount'             => $this->total_top_up_amount,
            'pending_topup_amount'            => $this->pending_topup_amount,
            'pending_topup_requested_by'      => $this->whenLoaded('pendingTopupRequestedBy', fn () => $this->pendingTopupRequestedBy?->username),
            'pending_topup_requested_at'      => $this->pending_topup_requested_at,
            'metadata'                         => $this->metadata,
            'total_withdrawal_amount'         => $this->total_withdrawal_amount,
            'total_interest_accrued'          => $this->total_interest_accrued,
            'total_interest_paid'             => $this->total_interest_paid,
            'outstanding_interest'            => $this->outstanding_interest,
            'total_fees_charged'              => $this->total_fees_charged,
            'total_penalties_charged'         => $this->total_penalties_charged,
            'total_withholding_tax_deducted'  => $this->total_withholding_tax_deducted,
            'net_interest_paid'               => $this->net_interest_paid,
            'maturity_value'                  => $this->maturity_value,
            'last_interest_payment_date'      => $this->last_interest_payment_date,
            'next_interest_payment_date'      => $this->next_interest_payment_date,

            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
