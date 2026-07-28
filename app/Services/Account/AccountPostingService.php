<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\Account\AccountStatus;
use App\Enums\Product\AccountProductType;
use App\Enums\Transaction\DebitCredit;
use App\Enums\Transaction\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\GeneralLedger\GeneralLedgerPostingService;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

// The single place allowed to mutate account_balance/available_balance — every channel (admin postings, mobile, internet banking) must go through debit()/credit() here. Callers must invoke from inside their own DB::transaction() so lockForUpdate() holds until commit. Every leg also mirrors into the account's product ACCOUNT_CONTROL GL so the trial balance stays reconciled.
class AccountPostingService
{
    public function __construct(
        private GeneralLedgerPostingService $glLedger,
    ) {}

    public function debit(Account $account, array $leg): Transaction
    {
        return $this->post($account, DebitCredit::DEBIT, $leg);
    }

    public function credit(Account $account, array $leg): Transaction
    {
        return $this->post($account, DebitCredit::CREDIT, $leg);
    }

    // Whether $amount fits within available_balance + overdraft allowance; used to pre-check before a posting is even created (re-enforced against the locked row in post()).
    public function hasSufficientFunds(Account $account, string $amount): bool
    {
        $overdraftLimit = $account->accountProduct?->allow_overdraft
            ? (string) $account->accountProduct->overdraft_limit
            : '0';

        return bccomp($amount, bcadd((string) $account->available_balance, $overdraftLimit, 4), 4) <= 0;
    }

    private function post(Account $account, DebitCredit $debitCredit, array $leg): Transaction
    {
        /** @var Account $account */
        $account = Account::with('accountProduct.generalLedgerMappings.generalLedger')
            ->whereKey($account->id)
            ->lockForUpdate()
            ->firstOrFail();

        $controlGeneralLedger = $account->accountProduct
            ?->generalLedgerMappings
            ->firstWhere('financial_account_type', AccountProductType::ACCOUNT_CONTROL->value)
            ?->generalLedger;

        if (! $controlGeneralLedger) {
            throw ValidationException::withMessages([
                'account_id' => ["Account '{$account->account_number}' has no ACCOUNT_CONTROL general ledger mapped — cannot post."],
            ]);
        }

        if ($account->status !== AccountStatus::ACTIVE->value) {
            throw ValidationException::withMessages([
                'account_id' => ["Account '{$account->account_number}' is not active (status: {$account->status})."],
            ]);
        }

        if ((int) $account->currency_id !== (int) $leg['currency_id']) {
            throw ValidationException::withMessages([
                'currency_id' => ["Account '{$account->account_number}' currency does not match the posting currency."],
            ]);
        }

        $amount = (string) $leg['amount'];

        if ($debitCredit === DebitCredit::DEBIT) {
            if ($account->pnd) {
                throw ValidationException::withMessages([
                    'account_id' => ["Account '{$account->account_number}' has a post-no-debit restriction."],
                ]);
            }

            if (! $this->hasSufficientFunds($account, $amount)) {
                throw ValidationException::withMessages([
                    'amount' => ["Account '{$account->account_number}' has insufficient funds for this debit."],
                ]);
            }
        } elseif ($account->pnc) {
            throw ValidationException::withMessages([
                'account_id' => ["Account '{$account->account_number}' has a post-no-credit restriction."],
            ]);
        }

        $balanceBefore = (string) $account->account_balance;
        $balanceAfter  = $debitCredit === DebitCredit::DEBIT
            ? bcsub($balanceBefore, $amount, 4)
            : bcadd($balanceBefore, $amount, 4);

        $account->update([
            'account_balance'     => $balanceAfter,
            'available_balance'   => bcsub($balanceAfter, (string) $account->lien_balance, 4),
            'last_transaction_at' => now(),
        ]);

        $transaction = Transaction::create([
            'financial_transaction_id' => $leg['financial_transaction_id'],
            'entry_group_id'           => $leg['entry_group_id'],
            'transaction_reference'    => $leg['transaction_reference'] ?? null,
            'reference'                => ReferenceGenerator::generate('TXN'),
            'account_id'               => $account->id,
            'customer_id'              => $account->customer_id,
            'posting_type'             => $leg['posting_type'] ?? null,
            'module'                   => $leg['module'] ?? null,
            'transaction_type'         => $leg['transaction_type'] ?? null,
            'transaction_subtype'      => $leg['transaction_subtype'] ?? null,
            'business_service'         => $leg['business_service'] ?? null,
            'channel'                  => $leg['channel'] ?? null,
            'service_provider'         => $leg['service_provider'] ?? null,
            'provider_reference'       => $leg['provider_reference'] ?? null,
            'counterparty_type'           => $leg['counterparty_type'] ?? null,
            'counterparty_id'             => $leg['counterparty_id'] ?? null,
            'counterparty_name'           => $leg['counterparty_name'] ?? null,
            'counterparty_account_number' => $leg['counterparty_account_number'] ?? null,
            'counterparty_bank_code'      => $leg['counterparty_bank_code'] ?? null,
            'counterparty_bank_name'      => $leg['counterparty_bank_name'] ?? null,
            'type'                     => $debitCredit->value,
            'direction'                => $debitCredit === DebitCredit::CREDIT ? 'inward' : 'outward',
            'amount'                   => $amount,
            'balance_before'           => $balanceBefore,
            'balance_after'            => $balanceAfter,
            'currency_id'              => $leg['currency_id'],
            'value_date'               => $leg['value_date'] ?? now()->toDateString(),
            'posting_date'             => $leg['posting_date'] ?? now()->toDateString(),
            'status'                   => TransactionStatus::COMPLETED->value,
            'narration'                => $leg['narration'] ?? null,
            'metadata'                 => $leg['metadata'] ?? null,
            'created_by'               => $leg['created_by'] ?? null,
        ]);

        // Mirror the same debit/credit into the account's control GL so the GL trial balance stays reconciled with the account subledger.
        $debitCredit === DebitCredit::DEBIT
            ? $this->glLedger->debit($controlGeneralLedger, $leg)
            : $this->glLedger->credit($controlGeneralLedger, $leg);

        Log::channel('accounts')->info('account_transaction', [
            'transaction_id'           => $transaction->id,
            'financial_transaction_id' => $leg['financial_transaction_id'],
            'entry_group_id'           => $leg['entry_group_id'],
            'account_id'               => $account->id,
            'account_number'           => $account->account_number,
            'customer_id'              => $account->customer_id,
            'debit_credit'             => $debitCredit->value,
            'amount'                   => $amount,
            'balance_before'           => $balanceBefore,
            'balance_after'            => $balanceAfter,
            'currency_id'              => $leg['currency_id'],
            'posting_date'             => $transaction->posting_date,
            'created_by'               => $leg['created_by'] ?? null,
        ]);

        return $transaction;
    }
}
