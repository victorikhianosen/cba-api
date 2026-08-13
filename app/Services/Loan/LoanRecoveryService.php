<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Enums\Product\LoanProductType;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanRecovery;
use App\Services\Account\AccountPostingService;
use App\Services\Audit\AuditService;
use App\Services\Posting\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Records money collected on a loan AFTER it's already been written off. The loan stays
// written_off (its principal is never restored — that would misrepresent the loan as
// still "on the books" as a real receivable) — recovered money is booked as its own
// income line (RECOVERY_INCOME), split across components for reporting via
// principal/interest/penalty/fee_portion, but posted as a single GL leg since it's all
// the same kind of event (cash landing that was already written off as a loss).
class LoanRecoveryService
{
    public function __construct(
        private PostingService $postings,
        private LoanTransactionService $transactions,
        private AuditService $audit,
        private AccountPostingService $accountPostings,
    ) {}

    public function listForLoan(Loan $loan)
    {
        return $loan->recoveries()->with(['recoveryOfficer', 'fundingAccount'])->latest('recovered_at')->get();
    }

    public function record(Loan $loan, array $data): LoanRecovery
    {
        if ($loan->status !== 'written_off') {
            throw ValidationException::withMessages(['status' => ['Recovery can only be recorded against a written-off loan.']]);
        }

        $amount = (string) $data['amount'];

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => ['Recovery amount must be greater than zero.']]);
        }

        $portions = [
            'principal_portion' => (string) ($data['principal_portion'] ?? $amount),
            'interest_portion'  => (string) ($data['interest_portion'] ?? '0'),
            'penalty_portion'   => (string) ($data['penalty_portion'] ?? '0'),
            'fee_portion'       => (string) ($data['fee_portion'] ?? '0'),
        ];

        $portionsSum = array_reduce($portions, fn ($carry, $value) => bcadd($carry, $value, 2), '0');

        if (bccomp($portionsSum, $amount, 2) !== 0) {
            throw ValidationException::withMessages(['amount' => ['The principal/interest/penalty/fee portions must add up to the total recovery amount.']]);
        }

        return DB::transaction(function () use ($loan, $data, $amount, $portions) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            $fundingAccount = Account::findOrFail($data['funding_account_id']);

            // Same as repayment — money recovered on a written-off loan must come out of
            // the borrowing customer's own account, never a different customer's.
            if ((int) $fundingAccount->customer_id !== (int) $loan->customer_id) {
                throw ValidationException::withMessages([
                    'funding_account_id' => ['The funding account must belong to the loan\'s own customer.'],
                ]);
            }

            if ((int) $fundingAccount->currency_id !== (int) $loan->currency_id) {
                throw ValidationException::withMessages([
                    'funding_account_id' => ['The funding account currency does not match the loan currency.'],
                ]);
            }

            if (! $this->accountPostings->hasSufficientFunds($fundingAccount, $amount)) {
                throw ValidationException::withMessages([
                    'funding_account_id' => ["Account '{$fundingAccount->account_number}' has insufficient funds for this recovery."],
                ]);
            }

            $product = $loan->loanProduct()->with('generalLedgerMappings.generalLedger')->first();
            $recoveryGl = $product->generalLedgerMappings
                ->firstWhere('financial_account_type', LoanProductType::RECOVERY_INCOME->value)
                ?->generalLedger;

            if (! $recoveryGl) {
                throw ValidationException::withMessages(['loan_product_id' => ['This loan product has no Recovery Income GL mapping configured.']]);
            }

            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'account',
                'source_id'        => $fundingAccount->id,
                'destination_type' => 'general_ledger',
                'destination_id'   => $recoveryGl->id,
                'amount'           => $amount,
                'module'           => 'loans',
                'transaction_type' => 'recovery',
                'remarks'          => "Recovery on written-off loan '{$loan->account_number}'.",
            ]);

            $actor = Auth::guard('user')->user();

            $recovery = $loan->recoveries()->create([
                ...$portions,
                'amount'               => $amount,
                'channel'              => $data['channel'] ?? null,
                'reference'            => $data['reference'] ?? null,
                'recovery_officer_id'   => $data['recovery_officer_id'] ?? null,
                'recovered_at'          => $data['recovered_at'] ?? now()->toDateString(),
                'funding_account_id'    => $fundingAccount->id,
                'notes'                => $data['notes'] ?? null,
                'created_by'            => $actor?->id,
            ]);

            foreach (['principal' => 'recovery_principal', 'interest' => 'recovery_interest', 'penalty' => 'recovery_penalty', 'fee' => 'recovery_fee'] as $key => $type) {
                $portionAmount = $portions["{$key}_portion"];

                if (bccomp($portionAmount, '0', 2) <= 0) {
                    continue;
                }

                $this->transactions->record(
                    loan: $loan,
                    type: $type,
                    amount: $portionAmount,
                    posting: $financialTransaction->posting,
                    description: "Recovery ({$key}) on written-off loan.",
                );
            }

            $this->audit->log(
                action: 'created',
                module: 'loan_recoveries',
                auditable: $recovery,
                after: $recovery->toArray(),
                description: "Recovery of ₦{$amount} recorded on written-off loan '{$loan->account_number}' by '{$actor?->username}'.",
            );

            return $recovery;
        });
    }
}
