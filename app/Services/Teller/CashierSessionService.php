<?php

declare(strict_types=1);

namespace App\Services\Teller;

use App\Enums\GeneralLedger\GeneralLedgerClassification;
use App\Enums\GeneralLedger\GeneralLedgerStatus;
use App\Enums\Teller\CashierSessionStatus;
use App\Enums\Teller\CashierTransactionStatus;
use App\Enums\Teller\CashierTransactionType;
use App\Enums\Teller\CashPointType;
use App\Enums\Teller\TellerStatus;
use App\Models\Account;
use App\Models\CashierAssignment;
use App\Models\CashierSession;
use App\Models\CashierTransaction;
use App\Models\GeneralLedger;
use App\Models\Posting;
use App\Models\Teller;
use App\Models\Vault;
use App\Services\Account\AccountService;
use App\Services\Audit\AuditService;
use App\Services\GeneralLedger\GeneralLedgerService;
use App\Services\Notification\NotificationService;
use App\Services\Posting\PostingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Drives the teller's daily open -> buy/sell -> close -> reconcile -> (variance approval)
// -> sweep-to-vault lifecycle. Every actual cash movement is a
// PostingService::createAndApprove() call (general_ledger <-> general_ledger) — this
// service owns none of the ledger mechanics itself, only the teller-domain rules
// (assignment/session exclusivity, guard rails, variance).
class CashierSessionService
{
    // A GL for cash-short/over write-offs, created lazily the first time a variance is
    // trued up — one shared suspense GL for the whole bank, not per-teller.
    private const VARIANCE_SUSPENSE_GL_CODE_NAME = 'Teller Variance Suspense';

    // Working capacity for the suspense GL — not real capital, just headroom so an overage
    // release is never blocked by hasSufficientBalance() on a fresh suspense GL.
    private const SUSPENSE_WORKING_BALANCE = '999999999999.000000';

    public function __construct(
        private AuditService $audit,
        private PostingService $postings,
        private GeneralLedgerService $generalLedgers,
        private AccountService $accounts,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?int $tellerId = null, ?int $cashierId = null, ?string $status = null): LengthAwarePaginator
    {
        return CashierSession::query()
            ->with(['teller', 'cashier'])
            ->when($tellerId, fn ($query, $tellerId) => $query->where('teller_id', $tellerId))
            ->when($cashierId, fn ($query, $cashierId) => $query->where('cashier_id', $cashierId))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): CashierSession
    {
        return CashierSession::with(['teller.vault', 'cashier', 'openedBy', 'closedBy', 'reconciledBy', 'varianceApprovedBy', 'transactions'])
            ->findOrFail($id);
    }

    // Withdrawal requests for the "Withdrawal Approvals" queue — defaults to only those
    // still awaiting a second admin's approval, but $status can widen this to show the
    // full approve/reject history (e.g. 'approved', 'rejected', or null for all).
    public function pendingWithdrawals(int $perPage = 15, ?string $status = CashierTransactionStatus::PENDING->value): LengthAwarePaginator
    {
        return CashierTransaction::query()
            ->with(['cashierSession.teller', 'requestedBy', 'posting.approvedBy'])
            ->where('type', CashierTransactionType::CASH_WITHDRAWAL->value)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    // The current cashier's own in-progress session, if any — for the "my teller" screen.
    public function mine(int $cashierId): ?CashierSession
    {
        return CashierSession::with(['teller.vault', 'transactions'])
            ->where('cashier_id', $cashierId)
            ->whereIn('status', [
                CashierSessionStatus::OPEN->value,
                CashierSessionStatus::PENDING_RECONCILIATION->value,
                CashierSessionStatus::PENDING_VARIANCE_APPROVAL->value,
            ])
            ->latest()
            ->first();
    }

    public function openSession(int $tellerId, int $cashierId, string $openingFloatAmount): CashierSession
    {
        $actor = Auth::guard('user')->user();

        $session = DB::transaction(function () use ($tellerId, $cashierId, $openingFloatAmount) {
            $teller = Teller::whereKey($tellerId)->lockForUpdate()->firstOrFail();

            if ($teller->status !== TellerStatus::ACTIVE->value) {
                throw ValidationException::withMessages(['teller_id' => ['Only an active teller can be opened.']]);
            }

            $assignment = CashierAssignment::where('teller_id', $teller->id)->whereNull('unassigned_at')->first();

            if (! $assignment || (int) $assignment->user_id !== $cashierId) {
                throw ValidationException::withMessages(['teller_id' => ['You are not the currently assigned cashier for this teller.']]);
            }

            if ($teller->currentSession()->exists()) {
                throw ValidationException::withMessages(['teller_id' => ['This teller already has an open session.']]);
            }

            if (CashierSession::where('cashier_id', $cashierId)->whereIn('status', [
                CashierSessionStatus::OPEN->value,
                CashierSessionStatus::PENDING_RECONCILIATION->value,
                CashierSessionStatus::PENDING_VARIANCE_APPROVAL->value,
            ])->exists()) {
                throw ValidationException::withMessages(['cashier_id' => ['You already have another teller session in progress.']]);
            }

            if ($teller->opening_float_ceiling_amount !== null && bccomp($openingFloatAmount, (string) $teller->opening_float_ceiling_amount, 6) > 0) {
                throw ValidationException::withMessages(['opening_float_amount' => ["Opening float cannot exceed this teller's ceiling of {$teller->opening_float_ceiling_amount}."]]);
            }

            $vault = Vault::findOrFail($teller->vault_id);

            // Teller/vault cash GLs are classified LIABILITY (debit decreases, credit
            // increases). The vault is giving cash away (decreases => debit => source);
            // the teller is receiving it (increases => credit => destination). This also
            // means the posting engine's source-side balance check correctly gates the
            // vault — it can't issue more float than it currently holds.
            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $vault->general_ledger_id,
                'destination_type' => 'general_ledger',
                'destination_id'   => $teller->general_ledger_id,
                'amount'           => $openingFloatAmount,
                'module'           => 'teller_management',
                'transaction_type' => 'teller_open_float',
                'remarks'          => "Opening float for teller '{$teller->name}' ({$teller->code}).",
            ]);

            $session = CashierSession::create([
                'teller_id'                => $teller->id,
                'cashier_id'               => $cashierId,
                'opened_by'                => Auth::guard('user')->id(),
                'opening_float_amount'     => $openingFloatAmount,
                'opening_posting_id'       => $financialTransaction->posting_id,
                'expected_closing_balance' => $openingFloatAmount,
                'status'                   => CashierSessionStatus::OPEN->value,
                'opened_at'                => now(),
            ])->refresh();

            CashierTransaction::create([
                'cashier_session_id' => $session->id,
                'posting_id'         => $financialTransaction->posting_id,
                'type'               => CashierTransactionType::CASH_BUY->value,
                'counterparty_type'  => CashPointType::VAULT->value,
                'counterparty_id'    => $vault->id,
                'amount'             => $openingFloatAmount,
                'running_balance'    => $openingFloatAmount,
                'narration'          => 'Opening float',
                'created_by'         => Auth::guard('user')->id(),
            ]);

            return $session;
        });

        $this->audit->log(
            action: 'opened',
            module: 'cashier_sessions',
            auditable: $session,
            after: $session->toArray(),
            description: "Cashier session #{$session->id} opened by '{$actor?->username}' with float {$openingFloatAmount}.",
        );

        return $session->load('teller', 'cashier');
    }

    public function buy(int $sessionId, string $counterpartyType, int $counterpartyId, string $amount, ?string $narration = null): CashierTransaction
    {
        return $this->move($sessionId, CashierTransactionType::CASH_BUY, $counterpartyType, $counterpartyId, $amount, $narration);
    }

    public function sell(int $sessionId, string $counterpartyType, int $counterpartyId, string $amount, ?string $narration = null): CashierTransaction
    {
        return $this->move($sessionId, CashierTransactionType::CASH_SELL, $counterpartyType, $counterpartyId, $amount, $narration);
    }

    // Shared buy/sell mechanics — a buy debits the counterparty's GL and credits the
    // teller's (teller cash goes up); a sell is the mirror. Both only permitted while the
    // session is OPEN and the teller is ACTIVE — closing a session immediately stops new
    // movements.
    private function move(int $sessionId, CashierTransactionType $type, string $counterpartyType, int $counterpartyId, string $amount, ?string $narration): CashierTransaction
    {
        $actor = Auth::guard('user')->user();

        $transaction = DB::transaction(function () use ($sessionId, $type, $counterpartyType, $counterpartyId, $amount, $narration) {
            $session = CashierSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status !== CashierSessionStatus::OPEN->value) {
                throw ValidationException::withMessages(['cashier_session_id' => ['This teller session is not open — no further cash movements are permitted.']]);
            }

            $teller = Teller::whereKey($session->teller_id)->lockForUpdate()->firstOrFail();

            if ($teller->status !== TellerStatus::ACTIVE->value) {
                throw ValidationException::withMessages(['teller_id' => ['This teller is closed or inactive — no cash operations are permitted.']]);
            }

            $counterpartyGlId = $this->resolveCounterpartyGlId($teller, $counterpartyType, $counterpartyId);

            $isBuy = $type === CashierTransactionType::CASH_BUY;

            // Both parties are LIABILITY GLs (debit decreases, credit increases). A buy
            // means the counterparty is giving cash away (decreases => debit => source)
            // and the teller is receiving it (increases => credit => destination); a sell
            // is the mirror. This also means the balance check always lands on whichever
            // party is actually giving up cash, not the one receiving it.
            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $isBuy ? $counterpartyGlId : $teller->general_ledger_id,
                'destination_type' => 'general_ledger',
                'destination_id'   => $isBuy ? $teller->general_ledger_id : $counterpartyGlId,
                'amount'           => $amount,
                'module'           => 'teller_management',
                'transaction_type' => $isBuy ? 'teller_cash_buy' : 'teller_cash_sell',
                'remarks'          => $narration ?: ($isBuy ? 'Teller cash buy' : 'Teller cash sell'),
            ]);

            $newBalance = $isBuy
                ? bcadd((string) $session->expected_closing_balance, $amount, 6)
                : bcsub((string) $session->expected_closing_balance, $amount, 6);

            $session->update(['expected_closing_balance' => $newBalance]);

            return CashierTransaction::create([
                'cashier_session_id' => $session->id,
                'posting_id'         => $financialTransaction->posting_id,
                'type'               => $type->value,
                'counterparty_type'  => $counterpartyType,
                'counterparty_id'    => $counterpartyId,
                'amount'             => $amount,
                'running_balance'    => $newBalance,
                'narration'          => $narration,
                'created_by'         => Auth::guard('user')->id(),
            ]);
        });

        $this->audit->log(
            action: $type->value,
            module: 'cashier_sessions',
            auditable: $transaction,
            after: $transaction->toArray(),
            description: "Cashier session #{$transaction->cashier_session_id} recorded a {$type->value} of {$amount} by '{$actor?->username}'.",
        );

        return $transaction;
    }

    private function resolveCounterpartyGlId(Teller $teller, string $counterpartyType, int $counterpartyId): int
    {
        if ($counterpartyType === CashPointType::VAULT->value) {
            $vault = Vault::findOrFail($counterpartyId);

            if ((int) $vault->id !== (int) $teller->vault_id) {
                throw ValidationException::withMessages(['counterparty_id' => ['This teller can only buy from / sell to its own linked vault.']]);
            }

            return $vault->general_ledger_id;
        }

        if ($counterpartyType === CashPointType::TELLER->value) {
            if (! $teller->allows_teller_to_teller_transfer) {
                throw ValidationException::withMessages(['counterparty_type' => ['Teller-to-teller transfers are not permitted for this teller.']]);
            }

            $otherTeller = Teller::findOrFail($counterpartyId);

            if ($otherTeller->status !== TellerStatus::ACTIVE->value) {
                throw ValidationException::withMessages(['counterparty_id' => ['The other teller is closed or inactive.']]);
            }

            return $otherTeller->general_ledger_id;
        }

        throw ValidationException::withMessages(['counterparty_type' => ["'{$counterpartyType}' must be 'vault' or 'teller'."]]);
    }

    // A customer handing cash to the teller — posts immediately, same as buy/sell (a
    // teller receiving money is a single-actor, immediate action; nothing is at risk).
    // Uses the teller's separate customer-cash GL (EXPENSE, unconstrained), not the
    // liability control GL used for vault/teller transfers — see TellerService::create()
    // for why the two can't be the same GL.
    public function deposit(int $sessionId, string $accountNumber, string $amount, ?string $narration = null): CashierTransaction
    {
        $actor = Auth::guard('user')->user();

        $transaction = DB::transaction(function () use ($sessionId, $accountNumber, $amount, $narration) {
            $session = CashierSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status !== CashierSessionStatus::OPEN->value) {
                throw ValidationException::withMessages(['cashier_session_id' => ['This teller session is not open — no further cash movements are permitted.']]);
            }

            $teller = Teller::whereKey($session->teller_id)->lockForUpdate()->firstOrFail();

            if ($teller->status !== TellerStatus::ACTIVE->value) {
                throw ValidationException::withMessages(['teller_id' => ['This teller is closed or inactive — no cash operations are permitted.']]);
            }

            $account = $this->accounts->findByAccountNumber($accountNumber);

            if (! $account) {
                throw ValidationException::withMessages(['account_number' => ["No account found with account number '{$accountNumber}'."]]);
            }

            $this->assertSameCurrency($teller, $account);

            // Customer-cash GL is EXPENSE (debit increases, unconstrained — no balance
            // check). Customer Account increases on credit (its own hardcoded polarity via
            // AccountPostingService). Both increase together, correctly, in one posting.
            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $teller->customer_cash_general_ledger_id,
                'destination_type' => 'account',
                'destination_id'   => $account->id,
                'amount'           => $amount,
                'module'           => 'teller_management',
                'transaction_type' => 'teller_cash_deposit',
                'remarks'          => $narration ?: "Cash deposit into account '{$account->account_number}'.",
            ]);

            $newBalance = bcadd((string) $session->expected_closing_balance, $amount, 6);
            $session->update(['expected_closing_balance' => $newBalance]);

            return CashierTransaction::create([
                'cashier_session_id' => $session->id,
                'posting_id'         => $financialTransaction->posting_id,
                'type'               => CashierTransactionType::CASH_DEPOSIT->value,
                'status'             => CashierTransactionStatus::APPROVED->value,
                'counterparty_type'  => 'account',
                'counterparty_id'    => $account->id,
                'amount'             => $amount,
                'running_balance'    => $newBalance,
                'narration'          => $narration,
                'created_by'         => Auth::guard('user')->id(),
            ]);
        });

        $this->audit->log(
            action: 'cash_deposit',
            module: 'cashier_sessions',
            auditable: $transaction,
            after: $transaction->toArray(),
            description: "Cashier session #{$transaction->cashier_session_id} recorded a deposit of {$amount} into account '{$accountNumber}' by '{$actor?->username}'.",
        );

        return $transaction;
    }

    // A customer taking cash out — unlike deposit(), this does NOT move money
    // immediately. It creates a PENDING posting (PostingService::create(), not
    // createAndApprove()) and a pending CashierTransaction; a second admin must call
    // approveWithdrawal() before the cash is actually handed out. expected_closing_balance
    // is untouched until approval — nothing has moved yet.
    public function withdraw(int $sessionId, string $accountNumber, string $amount, ?string $narration = null): CashierTransaction
    {
        $actor = Auth::guard('user')->user();

        $transaction = DB::transaction(function () use ($sessionId, $accountNumber, $amount, $narration) {
            $session = CashierSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status !== CashierSessionStatus::OPEN->value) {
                throw ValidationException::withMessages(['cashier_session_id' => ['This teller session is not open — no further cash movements are permitted.']]);
            }

            $teller = Teller::whereKey($session->teller_id)->lockForUpdate()->firstOrFail();

            if ($teller->status !== TellerStatus::ACTIVE->value) {
                throw ValidationException::withMessages(['teller_id' => ['This teller is closed or inactive — no cash operations are permitted.']]);
            }

            $account = $this->accounts->findByAccountNumber($accountNumber);

            if (! $account) {
                throw ValidationException::withMessages(['account_number' => ["No account found with account number '{$accountNumber}'."]]);
            }

            $this->assertSameCurrency($teller, $account);

            // Pending only — source=account (debited on approval, decreases; this is where
            // hasSufficientFunds() will correctly block an overdraw), destination=teller's
            // customer-cash GL (credited on approval, decreases under EXPENSE polarity).
            $posting = $this->postings->create([
                'source_type'      => 'account',
                'source_id'        => $account->id,
                'destination_type' => 'general_ledger',
                'destination_id'   => $teller->customer_cash_general_ledger_id,
                'amount'           => $amount,
                'module'           => 'teller_management',
                'transaction_type' => 'teller_cash_withdrawal',
                'remarks'          => $narration ?: "Cash withdrawal from account '{$account->account_number}'.",
            ]);

            return CashierTransaction::create([
                'cashier_session_id' => $session->id,
                'posting_id'         => $posting->id,
                'type'               => CashierTransactionType::CASH_WITHDRAWAL->value,
                'status'             => CashierTransactionStatus::PENDING->value,
                'counterparty_type'  => 'account',
                'counterparty_id'    => $account->id,
                'amount'             => $amount,
                'running_balance'    => $session->expected_closing_balance,
                'narration'          => $narration,
                'requested_by'       => Auth::guard('user')->id(),
                'created_by'         => Auth::guard('user')->id(),
            ]);
        });

        $this->audit->log(
            action: 'cash_withdrawal_requested',
            module: 'cashier_sessions',
            auditable: $transaction,
            after: $transaction->toArray(),
            description: "Cashier session #{$transaction->cashier_session_id} requested a withdrawal of {$amount} from account '{$accountNumber}' by '{$actor?->username}' — pending approval.",
        );

        $this->notifications->notifyPermission(
            'approve_teller_withdrawal',
            'withdrawal',
            $transaction->id,
            'New withdrawal request',
            "A cash withdrawal of {$amount} from account '{$accountNumber}' is awaiting your approval.",
        );

        return $transaction;
    }

    // Second-admin approval of a pending withdrawal. Reuses PostingService::approve()
    // directly — its self-approval guard (approver can't be the posting's own creator)
    // already blocks the requesting cashier from approving their own withdrawal, no extra
    // code needed for that here.
    public function approveWithdrawal(int $cashierTransactionId): CashierTransaction
    {
        $actor = Auth::guard('user')->user();

        $transaction = DB::transaction(function () use ($cashierTransactionId) {
            $transaction = CashierTransaction::whereKey($cashierTransactionId)->lockForUpdate()->firstOrFail();

            if ($transaction->type !== CashierTransactionType::CASH_WITHDRAWAL->value
                || $transaction->status !== CashierTransactionStatus::PENDING->value) {
                throw ValidationException::withMessages(['cashier_transaction_id' => ['Only a pending withdrawal request can be approved.']]);
            }

            $posting = Posting::whereKey($transaction->posting_id)->firstOrFail();

            $this->postings->approve($posting);

            $session = CashierSession::whereKey($transaction->cashier_session_id)->lockForUpdate()->firstOrFail();
            $newBalance = bcsub((string) $session->expected_closing_balance, (string) $transaction->amount, 6);
            $session->update(['expected_closing_balance' => $newBalance]);

            $transaction->update([
                'status'      => CashierTransactionStatus::APPROVED->value,
                'running_balance' => $newBalance,
            ]);

            return $transaction->refresh();
        });

        $this->audit->log(
            action: 'cash_withdrawal_approved',
            module: 'cashier_sessions',
            auditable: $transaction,
            after: $transaction->toArray(),
            description: "Withdrawal request #{$transaction->id} of {$transaction->amount} approved by '{$actor?->username}'.",
        );

        $this->notifications->notifyUser(
            $transaction->requestedBy,
            'withdrawal',
            $transaction->id,
            'Withdrawal approved',
            "Your withdrawal request of {$transaction->amount} was approved.",
        );

        return $transaction;
    }

    // Sends the withdrawal request back — no money moves, the posting itself is rejected
    // (PostingService::reject(), already exists) so it can't later be approved by mistake.
    public function rejectWithdrawal(int $cashierTransactionId, string $reason): CashierTransaction
    {
        $actor = Auth::guard('user')->user();

        $transaction = DB::transaction(function () use ($cashierTransactionId, $reason) {
            $transaction = CashierTransaction::whereKey($cashierTransactionId)->lockForUpdate()->firstOrFail();

            if ($transaction->type !== CashierTransactionType::CASH_WITHDRAWAL->value
                || $transaction->status !== CashierTransactionStatus::PENDING->value) {
                throw ValidationException::withMessages(['cashier_transaction_id' => ['Only a pending withdrawal request can be rejected.']]);
            }

            $posting = Posting::whereKey($transaction->posting_id)->firstOrFail();

            $this->postings->reject($posting, $reason);

            $transaction->update([
                'status'    => CashierTransactionStatus::REJECTED->value,
                'narration' => trim(($transaction->narration ?? '') . " [Rejected: {$reason}]"),
            ]);

            return $transaction->refresh();
        });

        $this->audit->log(
            action: 'cash_withdrawal_rejected',
            module: 'cashier_sessions',
            auditable: $transaction,
            after: ['status' => $transaction->status, 'reason' => $reason],
            description: "Withdrawal request #{$transaction->id} rejected by '{$actor?->username}': {$reason}.",
        );

        $this->notifications->notifyUser(
            $transaction->requestedBy,
            'withdrawal',
            $transaction->id,
            'Withdrawal rejected',
            "Your withdrawal request of {$transaction->amount} was rejected: {$reason}",
        );

        return $transaction;
    }

    // Cashier enters the physical count. Does NOT move any money yet — reconcile() (and,
    // if needed, approveVariance()) decide what happens next, so a cashier can't close and
    // walk away with an un-reviewed count.
    public function closeSession(int $sessionId, string $countedClosingAmount): CashierSession
    {
        $actor = Auth::guard('user')->user();

        $session = DB::transaction(function () use ($sessionId, $countedClosingAmount) {
            $session = CashierSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status !== CashierSessionStatus::OPEN->value) {
                throw ValidationException::withMessages(['cashier_session_id' => ['Only an open session can be closed.']]);
            }

            $session->update([
                'counted_closing_amount' => $countedClosingAmount,
                'closed_by'              => Auth::guard('user')->id(),
                'closed_at'              => now(),
                'status'                 => CashierSessionStatus::PENDING_RECONCILIATION->value,
            ]);

            return $session->refresh();
        });

        $this->audit->log(
            action: 'closed',
            module: 'cashier_sessions',
            auditable: $session,
            after: ['counted_closing_amount' => $countedClosingAmount],
            description: "Cashier session #{$session->id} closed by '{$actor?->username}' with a count of {$countedClosingAmount}.",
        );

        return $session;
    }

    // Computes the variance and either auto-clears it (in tolerance) or routes to a second
    // admin (out of tolerance). In-tolerance sessions are swept back to the vault
    // immediately; out-of-tolerance sessions wait on approveVariance() before any further
    // money moves.
    public function reconcile(int $sessionId): CashierSession
    {
        $actor = Auth::guard('user')->user();

        $session = DB::transaction(function () use ($sessionId) {
            $session = CashierSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status !== CashierSessionStatus::PENDING_RECONCILIATION->value) {
                throw ValidationException::withMessages(['cashier_session_id' => ['Only a session pending reconciliation can be reconciled.']]);
            }

            $teller = Teller::whereKey($session->teller_id)->lockForUpdate()->firstOrFail();

            $variance = bcsub((string) $session->counted_closing_amount, (string) $session->expected_closing_balance, 6);
            $session->update(['variance_amount' => $variance]);

            $inTolerance = bccomp(
                $this->absAmount($variance),
                (string) $teller->variance_tolerance_amount,
                6,
            ) <= 0;

            if ($inTolerance) {
                $session->update([
                    'status'        => CashierSessionStatus::RECONCILED->value,
                    'reconciled_by' => Auth::guard('user')->id(),
                    'reconciled_at' => now(),
                ]);

                return $this->sweep($session->refresh(), $teller);
            }

            $session->update(['status' => CashierSessionStatus::PENDING_VARIANCE_APPROVAL->value]);

            return $session->refresh();
        });

        $this->audit->log(
            action: 'reconciled',
            module: 'cashier_sessions',
            auditable: $session,
            after: ['status' => $session->status, 'variance_amount' => $session->variance_amount],
            description: "Cashier session #{$session->id} reconciled by '{$actor?->username}' — status now '{$session->status}'.",
        );

        if ($session->status === CashierSessionStatus::PENDING_VARIANCE_APPROVAL->value) {
            $this->notifications->notifyPermission(
                'approve_cashier_variance',
                'variance',
                $session->id,
                'New variance to review',
                "Cashier session #{$session->id} has an out-of-tolerance variance of {$session->variance_amount} awaiting your approval.",
            );
        }

        return $session;
    }

    // Second-admin sign-off on an out-of-tolerance variance. Self-approval blocked, same
    // guard style as PostingService::approve() — the closing cashier cannot sign off on
    // their own discrepancy. Also fires a variance_adjustment posting truing up the
    // teller's GL balance to the physically-counted amount, so the GL never silently
    // diverges from physical cash after a reconciled variance.
    public function approveVariance(int $sessionId, ?string $remarks = null): CashierSession
    {
        $actor = Auth::guard('user')->user();

        $session = DB::transaction(function () use ($sessionId, $remarks, $actor) {
            $session = CashierSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status !== CashierSessionStatus::PENDING_VARIANCE_APPROVAL->value) {
                throw ValidationException::withMessages(['cashier_session_id' => ['Only a session pending variance approval can be approved.']]);
            }

            if ($actor && (int) $session->closed_by === (int) $actor->id) {
                throw ValidationException::withMessages(['cashier_session_id' => ['You cannot approve a variance on a session you closed yourself.']]);
            }

            $teller = Teller::whereKey($session->teller_id)->lockForUpdate()->firstOrFail();

            $this->postVarianceAdjustment($session, $teller);

            $session->update([
                'variance_reason'      => $remarks ?? $session->variance_reason,
                'variance_approved_by' => Auth::guard('user')->id(),
                'variance_approved_at' => now(),
                'status'               => CashierSessionStatus::RECONCILED->value,
                'reconciled_by'        => Auth::guard('user')->id(),
                'reconciled_at'        => now(),
            ]);

            return $this->sweep($session->refresh(), $teller);
        });

        $this->audit->log(
            action: 'variance_approved',
            module: 'cashier_sessions',
            auditable: $session,
            after: ['status' => $session->status, 'variance_amount' => $session->variance_amount],
            description: "Cashier session #{$session->id} variance of {$session->variance_amount} approved by '{$actor?->username}'.",
        );

        $this->notifications->notifyUser(
            $session->closedBy,
            'variance',
            $session->id,
            'Variance approved',
            "The variance of {$session->variance_amount} on your cashier session #{$session->id} was approved.",
        );

        return $session;
    }

    // Sends the variance back to the cashier for a recount / escalation rather than
    // silently clearing it — a rejected variance does NOT transition status, so the teller
    // stays blocked until either a valid approveVariance() or a manual correction happens.
    public function rejectVariance(int $sessionId, string $reason): CashierSession
    {
        $actor = Auth::guard('user')->user();

        $session = CashierSession::findOrFail($sessionId);

        if ($session->status !== CashierSessionStatus::PENDING_VARIANCE_APPROVAL->value) {
            throw ValidationException::withMessages(['cashier_session_id' => ['Only a session pending variance approval can be rejected.']]);
        }

        $session->update(['variance_reason' => $reason]);

        $this->audit->log(
            action: 'variance_rejected',
            module: 'cashier_sessions',
            auditable: $session,
            after: ['variance_reason' => $reason],
            description: "Cashier session #{$session->id} variance was rejected by '{$actor?->username}' — cashier must recount or escalate.",
        );

        $this->notifications->notifyUser(
            $session->closedBy,
            'variance',
            $session->id,
            'Variance rejected',
            "The variance on your cashier session #{$session->id} was rejected: {$reason}",
        );

        return $session->refresh();
    }

    // Sweeps the teller's control GL back to the vault and closes the session. Only ever
    // called internally once a session is RECONCILED — never exposed as its own
    // cashier-facing action.
    //
    // Sweeps the CONTROL GL's own actual balance, not session->counted_closing_amount —
    // the counted amount is the *total* physical cash in the drawer (float/buy/sell +
    // customer deposits/withdrawals combined), but only the float/buy/sell portion ever
    // lived in the control GL to begin with; customer deposit/withdrawal cash lives in the
    // separate customer-cash suspense GL (see TellerService::create()'s reasoning), which
    // has no clean accounting basis for a direct expense-GL-to-liability-GL sweep posting
    // (same polarity conflict documented on that GL) and is intentionally left un-swept —
    // it accumulates as a running total a back-office admin can periodically clear via a
    // manual journal entry (see manual_journal_entries_allowed on that GL).
    private function sweep(CashierSession $session, Teller $teller): CashierSession
    {
        $vault = Vault::findOrFail($teller->vault_id);

        $controlGlBalance = (string) GeneralLedger::whereKey($teller->general_ledger_id)->value('balance');

        // Sweep is a sell to the vault: teller is giving cash away (decreases => debit =>
        // source), vault is receiving it (increases => credit => destination).
        $financialTransaction = $this->postings->createAndApprove([
            'source_type'      => 'general_ledger',
            'source_id'        => $teller->general_ledger_id,
            'destination_type' => 'general_ledger',
            'destination_id'   => $vault->general_ledger_id,
            'amount'           => $controlGlBalance,
            'module'           => 'teller_management',
            'transaction_type' => 'teller_close_sweep',
            'remarks'          => "End-of-day sweep for teller '{$teller->name}' ({$teller->code}).",
        ]);

        CashierTransaction::create([
            'cashier_session_id' => $session->id,
            'posting_id'         => $financialTransaction->posting_id,
            'type'               => CashierTransactionType::CASH_SELL->value,
            'counterparty_type'  => CashPointType::VAULT->value,
            'counterparty_id'    => $vault->id,
            'amount'             => $controlGlBalance,
            'running_balance'    => 0,
            'narration'          => 'End-of-day sweep to vault',
            'created_by'         => Auth::guard('user')->id(),
        ]);

        $session->update([
            'closing_posting_id' => $financialTransaction->posting_id,
            'status'              => CashierSessionStatus::CLOSED->value,
        ]);

        return $session->refresh();
    }

    private function postVarianceAdjustment(CashierSession $session, Teller $teller): void
    {
        $variance = (string) $session->variance_amount;

        if (bccomp($variance, '0', 6) === 0) {
            return;
        }

        $suspenseGl = $this->varianceSuspenseGl($teller);

        $isOverage = bccomp($variance, '0', 6) > 0;

        // Teller and suspense are both LIABILITY GLs (debit decreases, credit increases).
        // Shortage: the teller physically holds less than its books say, so it must be
        // written down (decreases => debit => source) with the shortfall parked in
        // suspense pending investigation (increases => credit => destination). Overage:
        // the teller holds more than its books say, so suspense releases the difference
        // into it (suspense decreases => debit => source; teller increases => credit =>
        // destination).
        $financialTransaction = $this->postings->createAndApprove([
            'source_type'      => 'general_ledger',
            'source_id'        => $isOverage ? $suspenseGl->id : $teller->general_ledger_id,
            'destination_type' => 'general_ledger',
            'destination_id'   => $isOverage ? $teller->general_ledger_id : $suspenseGl->id,
            'amount'           => $this->absAmount($variance),
            'module'           => 'teller_management',
            'transaction_type' => 'teller_variance_adjustment',
            'remarks'          => "Variance true-up for teller '{$teller->name}' ({$teller->code}) — session #{$session->id}.",
        ]);

        CashierTransaction::create([
            'cashier_session_id' => $session->id,
            'posting_id'         => $financialTransaction->posting_id,
            'type'               => CashierTransactionType::VARIANCE_ADJUSTMENT->value,
            'counterparty_type'  => null,
            'counterparty_id'    => null,
            'amount'             => $this->absAmount($variance),
            'running_balance'    => $session->expected_closing_balance,
            'narration'          => 'Variance true-up',
            'created_by'         => Auth::guard('user')->id(),
        ]);
    }

    // bcmath-safe abs() — avoids a float round-trip that could lose precision on large
    // decimal amounts, unlike abs((float) $value).
    // Deposit/withdraw would otherwise fail deep inside PostingService::verify() with a
    // generic "different currencies" message after locks are already taken. Checking it
    // here first, against the account's own currency, gives a clear, teller-domain error
    // (naming the two currencies) before any row is locked.
    private function assertSameCurrency(Teller $teller, Account $account): void
    {
        if ((int) $teller->currency_id === (int) $account->currency_id) {
            return;
        }

        $tellerCurrency  = $teller->currency?->code ?? "currency #{$teller->currency_id}";
        $accountCurrency = $account->currency?->code ?? "currency #{$account->currency_id}";

        throw ValidationException::withMessages([
            'account_number' => [
                "This teller operates in {$tellerCurrency}, but account '{$account->account_number}' is denominated in {$accountCurrency}. Cash operations require matching currencies.",
            ],
        ]);
    }

    private function absAmount(string $value): string
    {
        return bccomp($value, '0', 6) < 0 ? bcmul($value, '-1', 6) : $value;
    }

    private function varianceSuspenseGl(Teller $teller): GeneralLedger
    {
        $existing = GeneralLedger::where('name', self::VARIANCE_SUSPENSE_GL_CODE_NAME)
            ->where('currency_id', $teller->currency_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $suspenseGl = $this->generalLedgers->create([
            'name'                           => self::VARIANCE_SUSPENSE_GL_CODE_NAME,
            'type'                           => 'teller_variance_suspense',
            'classification'                 => GeneralLedgerClassification::LIABILITY->value,
            'currency_id'                    => (int) $teller->currency_id,
            'manual_journal_entries_allowed' => false,
            'status'                         => GeneralLedgerStatus::ACTIVE->value,
            'is_assigned'                    => true,
            'description'                    => 'Shared suspense GL for teller cash-short/over discrepancies pending investigation.',
        ]);

        $suspenseGl->update(['balance' => self::SUSPENSE_WORKING_BALANCE]);

        return $suspenseGl->refresh();
    }
}
