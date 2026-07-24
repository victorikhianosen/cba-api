<?php

declare(strict_types=1);

namespace App\Services\GeneralLedger;

use App\Enums\GeneralLedger\GeneralLedgerClassification;
use App\Enums\GeneralLedger\GeneralLedgerStatus;
use App\Enums\Transaction\DebitCredit;
use App\Models\GeneralLedger;
use App\Models\JournalEntry;
use Illuminate\Validation\ValidationException;

/**
 * The single place allowed to mutate general_ledgers.balance and write the
 * matching journal_entries row — mirrors AccountPostingService for the GL
 * side of a posting. Callers must invoke debit()/credit() from inside their
 * own DB::transaction() so lockForUpdate() holds until commit.
 */
class GeneralLedgerPostingService
{
    /**
     * Classifications whose normal balance increases on a debit (assets and
     * expenses). Liability/equity/income accounts increase on credit
     * instead — the mirror of this list.
     */
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

    /**
     * Whether a debit of $amount is safe for this GL's classification.
     * Debiting an asset/expense GL only increases its (debit-normal)
     * balance, so there's no ceiling. Debiting a liability/equity/income GL
     * decreases its (credit-normal) balance, so it can't go below zero.
     */
    public function hasSufficientBalance(GeneralLedger $generalLedger, string $amount): bool
    {
        $classification = GeneralLedgerClassification::from($generalLedger->classification);

        if (in_array($classification, self::NORMAL_DEBIT_CLASSIFICATIONS, true)) {
            return true;
        }

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

        return JournalEntry::create([
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
    }
}
