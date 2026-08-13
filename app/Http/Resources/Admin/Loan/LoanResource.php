<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'account_number'      => $this->account_number,
            'status'              => $this->status,

            'customer_id'         => $this->customer_id,
            'customer'            => $this->whenLoaded('customer', fn () => [
                'id'            => $this->customer->id,
                'cif_number'    => $this->customer->cif_number,
                'full_name'     => trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name,
                'email'         => $this->customer->email,
                'phone'         => $this->customer->phone,
            ]),

            'loan_product_id'     => $this->loan_product_id,
            'loan_product'        => $this->whenLoaded('loanProduct', fn () => [
                'id'   => $this->loanProduct->id,
                'name' => $this->loanProduct->name,
                'code' => $this->loanProduct->code,
            ]),

            'disbursement_account_id' => $this->disbursement_account_id,
            'disbursement_account'    => $this->whenLoaded('disbursementAccount', fn () => [
                'id'              => $this->disbursementAccount->id,
                'account_number'  => $this->disbursementAccount->account_number,
                'account_name'    => $this->disbursementAccount->account_name,
            ]),

            'currency_id'   => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),

            'branch_id'     => $this->branch_id,
            'branch'        => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),

            'loan_officer_id' => $this->loan_officer_id,
            'loan_officer'    => $this->whenLoaded('loanOfficer', fn () => $this->loanOfficer ? [
                'id'   => $this->loanOfficer->id,
                'name' => trim("{$this->loanOfficer->first_name} {$this->loanOfficer->last_name}"),
            ] : null),

            'principal_amount'  => $this->principal_amount,
            'loan_file_path'      => $this->loan_file_path,
            'loan_file_url'      => app(FileStorageService::class)->url($this->loan_file_path),

            'interest_rate'                       => $this->interest_rate,
            'interest_rate_frequency_type'         => $this->interest_rate_frequency_type,
            'interest_method'                     => $this->interest_method,
            'repayment_every'                     => $this->repayment_every,
            'repayment_frequency_type'             => $this->repayment_frequency_type,
            'duration'                 => $this->duration,
            'arrears_tolerance_amount'              => $this->arrears_tolerance_amount,
            'penalty_interest_rate'                  => $this->penalty_interest_rate,
            'payment_allocation_order'                => $this->payment_allocation_order,

            'start_date'     => $this->start_date,
            'maturity_date'   => $this->maturity_date,
            'next_due_date'   => $this->next_due_date,

            'outstanding_principal'        => $this->outstanding_principal,
            'outstanding_interest'         => $this->outstanding_interest,
            'total_principal_disbursed'    => $this->total_principal_disbursed,
            'total_principal_paid'         => $this->total_principal_paid,
            'total_interest_paid'          => $this->total_interest_paid,
            'total_fees_charged'           => $this->total_fees_charged,
            'total_fees_paid'              => $this->total_fees_paid,
            'total_penalties_charged'      => $this->total_penalties_charged,
            'total_penalties_paid'         => $this->total_penalties_paid,
            'overpayment_balance'          => $this->overpayment_balance,
            'last_repayment_date'          => $this->last_repayment_date,

            'days_in_arrears'      => $this->days_in_arrears,
            'npl_classification'   => $this->npl_classification,
            'npl_classified_at'    => $this->npl_classified_at,
            'written_off_at'       => $this->written_off_at,

            'interest_suspended'         => $this->interest_suspended,
            'interest_suspended_at'      => $this->interest_suspended_at,
            'suspended_interest_balance' => $this->suspended_interest_balance,

            // withCount()'d by both LoanService::list() and find() — the list uses these
            // for the "needs attention" column/filter, the detail page uses them for the
            // tab-bar badges (see LoanDetail.tsx) so a pending request is visible before
            // clicking into the Restructuring/Write-Off tab that actually holds it.
            'pending_restructures_count' => $this->pending_restructures_count,
            'pending_topups_count'        => $this->pending_topups_count,
            'pending_refinances_count'    => $this->pending_refinances_count,
            'pending_write_offs_count'    => $this->pending_write_offs_count,
            'pending_repayments_count'    => $this->pending_repayments_count,
            'pending_requests_count'      => $this->pending_restructures_count === null ? null : (
                (int) $this->pending_restructures_count
                + (int) $this->pending_topups_count
                + (int) $this->pending_refinances_count
                + (int) $this->pending_write_offs_count
                + (int) $this->pending_repayments_count
            ),

            'created_by'       => $this->created_by,
            'created_by_name'   => $this->whenLoaded('createdBy', fn () => $this->createdBy?->username),
            'reviewed_by'      => $this->reviewed_by,
            'reviewed_by_name'  => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->username),
            'reviewed_at' => $this->reviewed_at,
            'approved_by'      => $this->approved_by,
            'approved_by_name'  => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->username),
            'approved_at' => $this->approved_at,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
