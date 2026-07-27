<?php

declare(strict_types=1);

namespace App\Services\GeneralLedger;

use App\Enums\GeneralLedger\GeneralLedgerClassification;
use App\Enums\GeneralLedger\GeneralLedgerStatus;
use App\Enums\Transaction\DebitCredit;
use App\Models\GeneralLedger;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

// The single place allowed to mutate general_ledgers.balance and write the matching journal_entries row (GL-side mirror of AccountPostingService). Callers must invoke debit()/credit() from inside their own DB::transaction() so lockForUpdate() holds until commit.
class GeneralLedgerPostingService
{
    // Classifications whose normal balance increases on a debit (asset/expense); liability/equity/income increase on credit instead.
    private const NORMAL_DEBIT_CLASSIFICATIONS = [
        GeneralLedgerClassification::ASSET,
        GeneralLedgerClassification::EXPENSE,
    ];

    public function debit(GeneralLedger $generalLedger, array $leg): JournalEntry
    {
        return $this->post($generalLedger, DebitCredit::DEBIT, $leg);
    }

    public function credit(GeneralLedger $generalLedger, array $leg): JournalEntry
    {
        return $this->post($generalLedger, DebitCredit::CREDIT, $leg);
    }

    // A GL used as a posting's source can never move out more than it currently holds, regardless of classification — the balance is a hard ceiling, not just an accounting-direction nicety.
    public function hasSufficientBalance(GeneralLedger $generalLedger, string $amount): bool
    {
        return bccomp($amount, (string) $generalLedger->balance, 4) <= 0;
    }

    private function post(GeneralLedger $generalLedger, DebitCredit $entryType, array $leg): JournalEntry
    {
        /** @var GeneralLedger $generalLedger */
        $generalLedger = GeneralLedger::whereKey($generalLedger->id)->lockForUpdate()->firstOrFail();

        if ($generalLedger->status !== GeneralLedgerStatus::ACTIVE->value) {
            throw ValidationException::withMessages([
                'general_ledger_id' => ["General ledger '{$generalLedger->name}' is not active (status: {$generalLedger->status})."],
            ]);
        }

        if ((int) $generalLedger->currency_id !== (int) $leg['currency_id']) {
            throw ValidationException::withMessages([
                'currency_id' => ["General ledger '{$generalLedger->name}' currency does not match the posting currency."],
            ]);
        }

        $amount = (string) $leg['amount'];

        $classification    = GeneralLedgerClassification::from($generalLedger->classification);
        $increasesOnDebit  = in_array($classification, self::NORMAL_DEBIT_CLASSIFICATIONS, true);
        $increases         = $entryType === DebitCredit::DEBIT ? $increasesOnDebit : ! $increasesOnDebit;

        $balanceBefore = (string) $generalLedger->balance;
        $balanceAfter  = $increases
            ? bcadd($balanceBefore, $amount, 4)
            : bcsub($balanceBefore, $amount, 4);

        $generalLedger->update(['balance' => $balanceAfter]);

        $journalEntry = JournalEntry::create([
            'financial_transaction_id' => $leg['financial_transaction_id'],
            'entry_group_id'           => $leg['entry_group_id'],
            'general_ledger_id'        => $generalLedger->id,
            'entry_type'               => $entryType->value,
            'amount'                   => $amount,
            'balance_before'           => $balanceBefore,
            'balance_after'            => $balanceAfter,
            'currency_id'              => $leg['currency_id'],
            'cost_center'              => $leg['cost_center'] ?? null,
            'branch_id'                => $leg['branch_id'] ?? null,
            'value_date'               => $leg['value_date'] ?? now()->toDateString(),
            'posting_date'             => $leg['posting_date'] ?? now()->toDateString(),
            'narration'                => $leg['narration'] ?? null,
            'created_by'               => $leg['created_by'] ?? null,
        ]);

        Log::channel('general_ledger')->info('gl_journal_entry', [
            'journal_entry_id'         => $journalEntry->id,
            'financial_transaction_id' => $leg['financial_transaction_id'],
            'entry_group_id'           => $leg['entry_group_id'],
            'general_ledger_id'        => $generalLedger->id,
            'gl_code'                  => $generalLedger->gl_code,
            'gl_name'                  => $generalLedger->name,
            'entry_type'               => $entryType->value,
            'amount'                   => $amount,
            'balance_before'           => $balanceBefore,
            'balance_after'            => $balanceAfter,
            'currency_id'              => $leg['currency_id'],
            'posting_date'             => $journalEntry->posting_date,
            'created_by'               => $leg['created_by'] ?? null,
        ]);

        return $journalEntry;
    }
}
