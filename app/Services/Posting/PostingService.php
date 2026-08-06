<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\Posting\PostingStatus;
use App\Enums\Transaction\FinancialTransactionStatus;
use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Models\GeneralLedger;
use App\Models\Posting;
use App\Models\User;
use App\Services\Account\AccountPostingService;
use App\Services\Audit\AuditService;
use App\Services\GeneralLedger\GeneralLedgerPostingService;
use App\Services\Notification\NotificationService;
use App\Support\ReferenceGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;

// Posting → approval → ledger-write flow for all four combinations (GL↔GL, account↔account, account↔GL, GL↔account). AccountPostingService/GeneralLedgerPostingService remain the only places allowed to touch a balance — this class just resolves which one to call.
class PostingService
{
    public function __construct(
        private AccountPostingService $accountLedger,
        private GeneralLedgerPostingService $glLedger,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $from = null, ?string $to = null, ?string $search = null, ?string $status = null, ?string $type = null): LengthAwarePaginator
    {
        return $this->filtered($from, $to, $search, $status, $type)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    // A cursor, not a Collection — never loads more than one row into memory at a time,
    // so exporting stays flat regardless of how many postings match the filter.
    public function cursorForExport(?string $from = null, ?string $to = null, ?string $search = null, ?string $status = null, ?string $type = null): LazyCollection
    {
        return $this->filtered($from, $to, $search, $status, $type)
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursor();
    }

    public function countForFilter(?string $from = null, ?string $to = null, ?string $search = null, ?string $status = null, ?string $type = null): int
    {
        return $this->filtered($from, $to, $search, $status, $type)->count();
    }

    private function filtered(?string $from, ?string $to, ?string $search = null, ?string $status = null, ?string $type = null): Builder
    {
        return Posting::query()
            ->when($from, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($type, function ($query, $type) {
                $map = [
                    'gl_to_gl'              => ['general_ledger', 'general_ledger'],
                    'customer_to_gl'        => ['account', 'general_ledger'],
                    'gl_to_customer'        => ['general_ledger', 'account'],
                    'customer_to_customer'  => ['account', 'account'],
                ];

                if (! isset($map[$type])) {
                    return;
                }

                [$sourceType, $destinationType] = $map[$type];

                $query->where('source_type', $sourceType)->where('destination_type', $destinationType);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('transaction_reference', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%");
                });
            });
    }

    public function find(int $id): Posting
    {
        return Posting::with([
            'currency', 'financialTransaction.journalEntries.generalLedger', 'financialTransaction.transactions',
            'createdBy', 'reviewedBy', 'approvedBy', 'rejectedBy',
        ])->findOrFail($id);
    }

    // Get the details, validate them, save the posting as pending. idempotency_key is always generated here — the client never sends it. Currency is never accepted from the caller — it's always the source party's own currency, and verify() rejects a destination in a different one (no Dollar-to-Naira postings).
    public function create(array $data): Posting
    {
        $data['idempotency_key'] = (string) Str::uuid();

        [$source] = $this->verify($data['source_type'], (int) $data['source_id'], $data['destination_type'], (int) $data['destination_id'], (string) $data['amount']);

        $data['currency_id'] = (int) $source->currency_id;

        $actor  = Auth::guard('user')->user();
        $amount = (string) $data['amount'];
        $fee    = (string) ($data['fee'] ?? 0);
        $vat    = (string) ($data['vat'] ?? 0);
        $tax    = (string) ($data['tax'] ?? 0);

        $posting = DB::transaction(fn() => Posting::create([
            ...$data,
            'reference'             => ReferenceGenerator::generate($this->referencePrefixFor($data['module'] ?? null)),
            'transaction_reference' => ReferenceGenerator::generate('TRN'),
            'total_amount'          => bcadd(bcadd(bcadd($amount, $fee, 4), $vat, 4), $tax, 4),
            'status'                => PostingStatus::PENDING->value,
            'created_by'            => $actor?->id,
        ])->refresh());

        $this->audit->log(
            action: 'created',
            module: 'postings',
            auditable: $posting,
            after: $posting->toArray(),
            description: "Posting '{$posting->reference}' ({$data['source_type']} → {$data['destination_type']}) was created by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_postings',
            'posting',
            $posting->id,
            'New posting request',
            "Posting '{$posting->reference}' for {$amount} is awaiting your approval.",
        );

        return $posting;
    }

    // Column headers for the bulk-posting template/upload. Identifiers are human-friendly
    // (account number / gl code), not raw ids — resolved to source_id/destination_id here,
    // same as the manual create-posting form resolves a picked account/GL to its id.
    public const BULK_IMPORT_COLUMNS = [
        'source_type', 'source_identifier', 'destination_type', 'destination_identifier',
        'amount', 'module', 'transaction_type', 'remarks',
    ];

    // Human-readable column headers for the downloadable template — the raw snake_case
    // BULK_IMPORT_COLUMNS keys are an internal implementation detail and shouldn't be shown
    // to admins filling in the sheet. SimpleExcelReader::headersToSnakeCase() (used in
    // bulkImport() below) converts these labels straight back to the internal keys on
    // upload, so this is purely cosmetic — no change needed on the read side.
    private const BULK_IMPORT_COLUMN_LABELS = [
        'source_type'            => 'Source Type',
        'source_identifier'      => 'Source Identifier',
        'destination_type'       => 'Destination Type',
        'destination_identifier' => 'Destination Identifier',
        'amount'                 => 'Amount',
        'module'                 => 'Module',
        'transaction_type'       => 'Transaction Type',
        'remarks'                => 'Remarks',
    ];

    // Friendly values shown in the downloaded template for source_type/destination_type,
    // and accepted back on upload (case-insensitively) alongside the raw internal values —
    // see normalizePartyType() below.
    private const PARTY_TYPE_LEDGER   = 'Ledger';
    private const PARTY_TYPE_CUSTOMER = 'Customer';

    /** One example row per posting type (GL to GL, Customer to GL, GL to Customer, Customer
     *  to Customer), so the template demonstrates every combination an admin can create.
     *  @return array<int, array<string, mixed>> */
    public function bulkImportTemplateRows(): array
    {
        $rows = [
            [
                'source_type'             => self::PARTY_TYPE_LEDGER,
                'source_identifier'       => '10000001',
                'destination_type'        => self::PARTY_TYPE_LEDGER,
                'destination_identifier'  => '10000002',
                'amount'                  => '10000.00',
                'module'                  => 'postings',
                'transaction_type'        => 'bulk_gl_transfer',
                'remarks'                 => 'Sample GL to GL transfer',
            ],
            [
                'source_type'             => self::PARTY_TYPE_CUSTOMER,
                'source_identifier'       => '1000000001',
                'destination_type'        => self::PARTY_TYPE_LEDGER,
                'destination_identifier'  => '10000001',
                'amount'                  => '7500.00',
                'module'                  => 'postings',
                'transaction_type'        => 'bulk_debit',
                'remarks'                 => 'Sample customer to GL debit',
            ],
            [
                'source_type'             => self::PARTY_TYPE_LEDGER,
                'source_identifier'       => '10000001',
                'destination_type'        => self::PARTY_TYPE_CUSTOMER,
                'destination_identifier'  => '1000000001',
                'amount'                  => '5000.00',
                'module'                  => 'postings',
                'transaction_type'        => 'bulk_credit',
                'remarks'                 => 'Sample GL to customer credit',
            ],
            [
                'source_type'             => self::PARTY_TYPE_CUSTOMER,
                'source_identifier'       => '1000000001',
                'destination_type'        => self::PARTY_TYPE_CUSTOMER,
                'destination_identifier'  => '1000000002',
                'amount'                  => '2500.00',
                'module'                  => 'postings',
                'transaction_type'        => 'bulk_transfer',
                'remarks'                 => 'Sample customer to customer transfer',
            ],
        ];

        return array_map(fn (array $row) => $this->relabelBulkImportRow($row), $rows);
    }

    // Accepts either the raw internal value ("account"/"general_ledger") or the friendly
    // template value ("Customer"/"Ledger"), case-insensitively, and normalizes to the
    // internal value that source_type/destination_type are actually stored as.
    private function normalizePartyType(string $value): string
    {
        return match (strtolower(trim($value))) {
            'ledger', 'gl', 'general_ledger', 'general ledger' => 'general_ledger',
            'customer', 'account'                              => 'account',
            default                                             => strtolower(trim($value)),
        };
    }

    // Swaps a row's internal snake_case keys for the human-readable headers an admin should
    // actually see in the downloaded template (see BULK_IMPORT_COLUMN_LABELS above).
    private function relabelBulkImportRow(array $row): array
    {
        $labelled = [];

        foreach ($row as $column => $value) {
            $labelled[self::BULK_IMPORT_COLUMN_LABELS[$column] ?? $column] = $value;
        }

        return $labelled;
    }

    // The "GL Reference" sheet's rows — every active GL's code/name/currency/classification/
    // balance, so whoever fills in the Postings sheet can look up a valid gl_code without
    // leaving the workbook. Cursor-based since a large chart of accounts shouldn't be loaded
    // into memory all at once.
    public function glReferenceRows(): LazyCollection
    {
        return GeneralLedger::query()
            ->with('currency:id,code')
            ->where('status', 'active')
            ->orderBy('gl_code')
            ->cursor()
            ->map(fn (GeneralLedger $gl) => [
                'GL Code'        => $gl->gl_code,
                'Name'           => $gl->name,
                'Currency'       => $gl->currency?->code,
                'Classification' => $gl->classification,
                'Balance'        => (string) $gl->balance,
            ]);
    }

    /**
     * Parses an uploaded spreadsheet's "Postings" sheet and creates one pending posting per
     * row, isolating each row in its own try/catch (same pattern as bulkApprove()) so a bad
     * row (unknown account number, insufficient funds, whatever) never blocks or rolls back
     * the rest of the batch. Every posting still lands as PENDING — this only automates data
     * entry, not the maker-checker approval step itself; a different admin still has to
     * approve each one (or use bulkApprove()) same as a manually created posting.
     *
     * @return array{created: int, failed: int, errors: array<int, array{row: int, reason: string}>}
     */
    public function bulkImport(UploadedFile $file): array
    {
        $created = 0;
        $errors  = [];

        $reader = SimpleExcelReader::create($file->getRealPath(), $file->getClientOriginalExtension());

        $rows = ($reader->hasSheet('Postings') ? $reader->fromSheetName('Postings') : $reader)
            ->headersToSnakeCase()
            ->getRows();

        $rowNumber = 1; // Row 1 is the header row, so the first data row is row 2.

        foreach ($rows as $row) {
            $rowNumber++;

            try {
                $data = $this->mapBulkImportRow($row);
                $this->create($data);
                $created++;
            } catch (ValidationException $e) {
                $errors[] = ['row' => $rowNumber, 'reason' => implode(' ', collect($e->errors())->flatten()->all())];
            } catch (\Throwable $e) {
                report($e);
                $errors[] = ['row' => $rowNumber, 'reason' => 'This row could not be processed — please check its values and try again.'];
            }
        }

        return ['created' => $created, 'failed' => count($errors), 'errors' => $errors];
    }

    /** @param array<string, mixed> $row */
    private function mapBulkImportRow(array $row): array
    {
        $sourceType      = $this->normalizePartyType((string) ($row['source_type'] ?? ''));
        $destinationType = $this->normalizePartyType((string) ($row['destination_type'] ?? ''));

        if (! in_array($sourceType, ['account', 'general_ledger'], true)) {
            throw ValidationException::withMessages(['source_type' => ["'{$row['source_type']}' must be 'Customer' or 'Ledger'."]]);
        }

        if (! in_array($destinationType, ['account', 'general_ledger'], true)) {
            throw ValidationException::withMessages(['destination_type' => ["'{$row['destination_type']}' must be 'Customer' or 'Ledger'."]]);
        }

        $amount = trim((string) ($row['amount'] ?? ''));

        if ($amount === '' || bccomp($amount, '0', 6) <= 0) {
            throw ValidationException::withMessages(['amount' => ['Amount must be greater than zero.']]);
        }

        $remarks = trim((string) ($row['remarks'] ?? ''));

        if ($remarks === '') {
            throw ValidationException::withMessages(['remarks' => ['Remarks is required.']]);
        }

        return [
            'source_type'      => $sourceType,
            'source_id'        => $this->resolvePartyId($sourceType, trim((string) ($row['source_identifier'] ?? ''))),
            'destination_type' => $destinationType,
            'destination_id'   => $this->resolvePartyId($destinationType, trim((string) ($row['destination_identifier'] ?? ''))),
            'amount'           => $amount,
            'module'           => trim((string) ($row['module'] ?? '')) ?: 'postings',
            'transaction_type' => trim((string) ($row['transaction_type'] ?? '')) ?: null,
            'remarks'          => $remarks,
        ];
    }

    private function resolvePartyId(string $type, string $identifier): int
    {
        if ($identifier === '') {
            throw ValidationException::withMessages(['identifier' => ['An account number or GL code is required for every party.']]);
        }

        if ($type === 'account') {
            $account = Account::where('account_number', $identifier)->first();

            if (! $account) {
                throw ValidationException::withMessages(['source_identifier' => ["No account found with account number '{$identifier}'."]]);
            }

            return $account->id;
        }

        $gl = GeneralLedger::where('gl_code', $identifier)->first();

        if (! $gl) {
            throw ValidationException::withMessages(['source_identifier' => ["No GL found with code '{$identifier}'."]]);
        }

        return $gl->id;
    }

    // The atomic core — verify, debit, credit, record, all in one DB::transaction, so any failure rolls back everything and leaves the posting exactly as pending as before.
    public function approve(Posting $posting): FinancialTransaction
    {
        if ($posting->status !== PostingStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'status' => ['Only pending postings can be approved.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor && (int) $posting->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a posting you created yourself.'],
            ]);
        }

        $financialTransaction = DB::transaction(function () use ($posting, $actor) {
            // Re-check under a row lock: the unlocked check above is only a fast-path
            // for the common case. Without this, two concurrent approve() calls for
            // the same posting (two admins clicking at once, or a bulk-approve batch
            // racing a single approve) could both pass the earlier check and both post
            // — this lock plus the pending guard is what actually prevents that.
            $lockedPosting = Posting::whereKey($posting->id)->lockForUpdate()->firstOrFail();

            if ($lockedPosting->status !== PostingStatus::PENDING->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending postings can be approved.'],
                ]);
            }

            return $this->execute($posting, $actor);
        });

        $this->audit->log(
            action: 'approved',
            module: 'postings',
            auditable: $posting,
            after: ['status' => PostingStatus::APPROVED->value, 'financial_transaction_id' => $financialTransaction->id],
            description: "Posting '{$posting->reference}' was approved by '{$actor?->username}'.",
        );

        $this->notifications->notifyUser(
            $posting->createdBy,
            'posting',
            $posting->id,
            'Posting approved',
            "Your posting '{$posting->reference}' was approved.",
        );

        return $financialTransaction;
    }

    // For system-authorized flows where the "approval" already happened one level up
    // (e.g. approving an investment IS the authorization to move the customer's money
    // into it) — there is no separate human left to be the checker, so creating the
    // posting and executing it happen atomically here instead of the normal
    // create()-then-someone-else-approve() two-step. Still goes through the exact
    // same verify()/execute() path as a manually-approved posting — same balance
    // checks, same ledger legs, same financial_transaction/audit trail — it just
    // skips the "pending, waiting for a second admin" state entirely.
    public function createAndApprove(array $data): FinancialTransaction
    {
        $data['idempotency_key'] = (string) Str::uuid();

        [$source] = $this->verify($data['source_type'], (int) $data['source_id'], $data['destination_type'], (int) $data['destination_id'], (string) $data['amount']);

        $data['currency_id'] = (int) $source->currency_id;

        $actor  = Auth::guard('user')->user();
        $amount = (string) $data['amount'];
        $fee    = (string) ($data['fee'] ?? 0);
        $vat    = (string) ($data['vat'] ?? 0);
        $tax    = (string) ($data['tax'] ?? 0);

        $financialTransaction = DB::transaction(function () use ($data, $amount, $fee, $vat, $tax, $actor) {
            $posting = Posting::create([
                ...$data,
                'reference'             => ReferenceGenerator::generate($this->referencePrefixFor($data['module'] ?? null)),
                'transaction_reference' => ReferenceGenerator::generate('TRN'),
                'total_amount'          => bcadd(bcadd(bcadd($amount, $fee, 4), $vat, 4), $tax, 4),
                'status'                => PostingStatus::PENDING->value,
                'created_by'            => $actor?->id,
            ])->refresh();

            return $this->execute($posting, $actor);
        });

        $this->audit->log(
            action: 'approved',
            module: 'postings',
            auditable: $financialTransaction,
            after: ['status' => PostingStatus::APPROVED->value, 'financial_transaction_id' => $financialTransaction->id],
            description: "Posting '{$financialTransaction->posting?->reference}' was created and immediately approved by '{$actor?->username}' (system-authorized).",
        );

        return $financialTransaction;
    }

    // Shared debit/credit/record core for a pending, already-locked posting. Callers
    // (approve(), createAndApprove()) are responsible for the pending/lock checks that
    // make sense for their own flow before calling this.
    private function execute(Posting $posting, ?User $actor): FinancialTransaction
    {
        [$source, $destination] = $this->verify(
            $posting->source_type,
            $posting->source_id,
            $posting->destination_type,
            $posting->destination_id,
            (string) $posting->amount,
        );

        $financialTransaction = $this->recordFinancialTransaction($posting, $source, $destination, $actor);

        $baseLeg = [
            'financial_transaction_id' => $financialTransaction->id,
            'entry_group_id'           => (string) Str::uuid(),
            'transaction_reference'    => $posting->transaction_reference,
            'amount'                   => $posting->amount,
            'currency_id'              => $posting->currency_id,
            'narration'                => $posting->remarks,
            'created_by'               => $actor?->id,
            'module'                   => $posting->module,
            'transaction_type'         => $posting->transaction_type,
            'transaction_subtype'      => $posting->transaction_subtype,
            'business_service'         => $posting->business_service,
            'channel'                  => $posting->channel,
        ];

        // Each leg's "counterparty" is the OTHER party in the posting — denormalized
        // onto the transaction row so "who sent/received this" never needs a join.
        $sourceSnapshot      = $this->partySnapshot($source, $posting->source_type);
        $destinationSnapshot = $this->partySnapshot($destination, $posting->destination_type);

        $debitLeg = [
            ...$baseLeg,
            'counterparty_type'           => $destinationSnapshot['type'],
            'counterparty_id'             => $destinationSnapshot['id'],
            'counterparty_name'           => $destinationSnapshot['name'],
            'counterparty_account_number' => $destinationSnapshot['code'],
        ];

        $creditLeg = [
            ...$baseLeg,
            'counterparty_type'           => $sourceSnapshot['type'],
            'counterparty_id'             => $sourceSnapshot['id'],
            'counterparty_name'           => $sourceSnapshot['name'],
            'counterparty_account_number' => $sourceSnapshot['code'],
        ];

        $posting->source_type === 'account'
            ? $this->accountLedger->debit($source, $debitLeg)
            : $this->glLedger->debit($source, $debitLeg);

        $posting->destination_type === 'account'
            ? $this->accountLedger->credit($destination, $creditLeg)
            : $this->glLedger->credit($destination, $creditLeg);

        $posting->update([
            'status'      => PostingStatus::APPROVED->value,
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ]);

        return $financialTransaction->load(['journalEntries', 'transactions']);
    }

    // Approves each posting independently — a rejection-worthy failure on one (insufficient
    // funds, self-approval, already-approved) never blocks or rolls back the others, since
    // approve() already wraps each posting in its own DB::transaction.
    public function bulkApprove(array $ids): array
    {
        $results = [];

        foreach ($ids as $id) {
            try {
                $posting = $this->find((int) $id);

                if ($posting->status !== PostingStatus::PENDING->value) {
                    throw ValidationException::withMessages([
                        'status' => ['Only pending postings can be approved.'],
                    ]);
                }

                $this->approve($posting);

                $results[] = ['id' => $id, 'reference' => $posting->reference, 'status' => 'approved'];
            } catch (ValidationException $e) {
                $results[] = ['id' => $id, 'status' => 'failed', 'reason' => $e->getMessage()];
            } catch (\Throwable $e) {
                report($e);
                $results[] = ['id' => $id, 'status' => 'failed', 'reason' => 'We are unable to process this posting, please try again.'];
            }
        }

        return $results;
    }

    public function reject(Posting $posting, string $reason): Posting
    {
        $actor = Auth::guard('user')->user();

        DB::transaction(function () use ($posting, $reason, $actor) {
            // Locked + re-checked so this can't race a concurrent approve() (or bulk-approve)
            // of the same posting — without this, reject() could overwrite the status to
            // REJECTED after approve() already moved money and posted the ledger legs.
            $lockedPosting = Posting::whereKey($posting->id)->lockForUpdate()->firstOrFail();

            if ($lockedPosting->status !== PostingStatus::PENDING->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending postings can be rejected.'],
                ]);
            }

            $posting->update([
                'status'           => PostingStatus::REJECTED->value,
                'reviewed_by'      => $actor?->id,
                'reviewed_at'      => now(),
                'rejected_by'      => $actor?->id,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);
        });

        $posting->refresh();

        $this->audit->log(
            action: 'rejected',
            module: 'postings',
            auditable: $posting,
            after: ['status' => PostingStatus::REJECTED->value, 'rejection_reason' => $reason],
            description: "Posting '{$posting->reference}' was rejected by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyUser(
            $posting->createdBy,
            'posting',
            $posting->id,
            'Posting rejected',
            "Your posting '{$posting->reference}' was rejected: {$reason}",
        );

        return $posting;
    }

    // Resolves source/destination, locks both rows (fixed [type, id] order to avoid deadlocks), checks currency match and that the source can cover the debit. Currency is never taken from a caller-supplied value — it's derived from the parties themselves, so a Dollar-to-Naira posting is structurally impossible, not just rejected after the fact.
    // Lets a posting's own reference identify which module it came from at a glance
    // (IVT2608... vs PST2608...) instead of every posting looking generic regardless of
    // origin — investments-module postings (funding, top-up) get "IVT", everything else
    // keeps the existing generic "PST" prefix.
    private const MODULE_REFERENCE_PREFIXES = [
        'investments'      => 'IVT',
        'teller_management' => 'TEL',
    ];

    private function referencePrefixFor(?string $module): string
    {
        return self::MODULE_REFERENCE_PREFIXES[$module] ?? 'PST';
    }

    private function verify(string $sourceType, int $sourceId, string $destinationType, int $destinationId, string $amount): array
    {
        if ($sourceType === $destinationType && $sourceId === $destinationId) {
            throw ValidationException::withMessages([
                'destination_id' => ['A posting cannot move money from a party to itself.'],
            ]);
        }

        $parties = [
            ['type' => $sourceType, 'id' => $sourceId],
            ['type' => $destinationType, 'id' => $destinationId],
        ];

        usort($parties, fn(array $a, array $b) => $a['type'] === $b['type']
            ? $a['id'] <=> $b['id']
            : $a['type'] <=> $b['type']);

        $locked = [];

        foreach ($parties as $party) {
            $class = Relation::getMorphedModel($party['type']);

            if (! $class) {
                throw ValidationException::withMessages([
                    'source_type' => ["'{$party['type']}' is not a postable party type."],
                ]);
            }

            $locked["{$party['type']}:{$party['id']}"] = $class::whereKey($party['id'])->lockForUpdate()->firstOrFail();
        }

        $source      = $locked["{$sourceType}:{$sourceId}"];
        $destination = $locked["{$destinationType}:{$destinationId}"];

        if ((int) $source->currency_id !== (int) $destination->currency_id) {
            throw ValidationException::withMessages([
                'destination_id' => ['Cannot post between parties with different currencies (e.g. Dollar to Naira) — source and destination must share the same currency.'],
            ]);
        }

        $hasSufficientBalance = $sourceType === 'account'
            ? $this->accountLedger->hasSufficientFunds($source, $amount)
            : $this->glLedger->hasSufficientBalance($source, $amount);

        if (! $hasSufficientBalance) {
            throw ValidationException::withMessages([
                'amount' => ["Posting amount exceeds the source {$sourceType}'s available balance."],
            ]);
        }

        return [$source, $destination];
    }

    // A display-ready snapshot of a party (account or GL) at the moment of posting —
    // 'code' is the account number for an account, the gl_code for a GL.
    private function partySnapshot(Model $party, string $type): array
    {
        return $type === 'account'
            ? ['type' => 'account', 'id' => $party->id, 'name' => $party->account_name, 'code' => $party->account_number]
            : ['type' => 'general_ledger', 'id' => $party->id, 'name' => $party->name, 'code' => $party->gl_code];
    }

    private function recordFinancialTransaction(Posting $posting, Model $source, Model $destination, ?User $actor): FinancialTransaction
    {
        $customerId = match (true) {
            $source instanceof Account      => $source->customer_id,
            $destination instanceof Account => $destination->customer_id,
            default                         => null,
        };

        $financialTransaction = FinancialTransaction::create([
            'reference'             => ReferenceGenerator::generate('FRN'),
            'transaction_reference' => $posting->transaction_reference,
            'idempotency_key'       => $posting->idempotency_key,
            'posting_id'            => $posting->id,
            'module'                => $posting->module,
            'transaction_type'      => $posting->transaction_type,
            'transaction_subtype'   => $posting->transaction_subtype,
            'business_service'      => $posting->business_service,
            'channel'               => $posting->channel,
            'customer_id'           => $customerId,
            'amount'                => $posting->amount,
            'fee'                   => $posting->fee,
            'commission'            => 0,
            'vat'                   => $posting->vat,
            'discount'              => 0,
            'currency_id'           => $posting->currency_id,
            'exchange_rate'         => $posting->exchange_rate,
            'status'                => FinancialTransactionStatus::COMPLETED->value,
            'narration'             => $posting->remarks,
            'created_by'            => $actor?->id,
            'processed_at'          => now(),
            'completed_at'          => now(),
        ]);

        Log::channel('transactions')->info('financial_transaction', [
            'financial_transaction_id' => $financialTransaction->id,
            'transaction_reference'    => $financialTransaction->transaction_reference,
            'posting_id'               => $posting->id,
            'reference'                => $posting->reference,
            'source_type'              => $posting->source_type,
            'source_id'                => $posting->source_id,
            'destination_type'         => $posting->destination_type,
            'destination_id'           => $posting->destination_id,
            'amount'                   => $financialTransaction->amount,
            'fee'                      => $financialTransaction->fee,
            'vat'                      => $financialTransaction->vat,
            'currency_id'              => $financialTransaction->currency_id,
            'status'                   => $financialTransaction->status,
            'created_by'               => $actor?->id,
        ]);

        return $financialTransaction;
    }




}
