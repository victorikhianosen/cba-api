<?php

declare(strict_types=1);

namespace App\Services\Teller;

use App\Enums\GeneralLedger\GeneralLedgerClassification;
use App\Enums\GeneralLedger\GeneralLedgerStatus;
use App\Enums\Teller\TellerStatus;
use App\Models\Branch;
use App\Models\CashierAssignment;
use App\Models\Teller;
use App\Models\Vault;
use App\Services\Audit\AuditService;
use App\Services\GeneralLedger\GeneralLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TellerService
{
    public function __construct(
        private AuditService $audit,
        private GeneralLedgerService $generalLedgers,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Teller::query()
            ->with(['branch', 'vault', 'currency', 'generalLedger', 'customerCashGeneralLedger', 'currentAssignment.user', 'currentSession'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Teller
    {
        return Teller::with([
            'branch', 'vault', 'currency', 'generalLedger', 'customerCashGeneralLedger',
            'createdBy', 'approvedBy',
            'currentAssignment.user', 'currentSession',
            'assignments.user', 'assignments.assignedBy', 'assignments.unassignedBy',
        ])->findOrFail($id);
    }

    // No separate "product" entity — a teller's policy (float ceiling, variance tolerance,
    // approval requirement, transfer permission) is set directly here at creation.
    public function create(array $data): Teller
    {
        $actor = Auth::guard('user')->user();

        $teller = DB::transaction(function () use ($data) {
            $vault  = Vault::findOrFail($data['vault_id']);
            $branch = Branch::findOrFail($data['branch_id']);

            // Classified LIABILITY, not ASSET — a "control" GL that the posting engine's
            // source-side balance check (hasSufficientBalance()) needs to correctly gate
            // as "the giving party can't move out more than it holds" must decrease on
            // debit, which is liability polarity. Every vault/teller cash movement in this
            // module treats whichever party is giving cash as the posting source (debited,
            // decreases) and the receiver as destination (credited, increases) — see
            // CashierSessionService.
            $generalLedger = $this->generalLedgers->create([
                'name'                            => "Teller Cash — {$data['name']}",
                'type'                            => 'teller_cash',
                'classification'                  => GeneralLedgerClassification::LIABILITY->value,
                'currency_id'                     => (int) $data['currency_id'],
                'manual_journal_entries_allowed'  => false,
                'status'                           => GeneralLedgerStatus::ACTIVE->value,
                'is_assigned'                      => true,
                'description'                      => "Cash-in-drawer GL for teller '{$data['name']}' (vault/teller-to-teller transfers only).",
            ]);

            // Separate GL for customer-facing deposit/withdrawal — needs opposite polarity
            // from the liability control GL above (a deposit must increase both the
            // customer's account AND the teller's cash simultaneously, which is only
            // possible with complementary classifications). Classified EXPENSE, not ASSET,
            // so it's in GeneralLedgerPostingService::UNCONSTRAINED_CLASSIFICATIONS — a
            // fresh GL at 0 balance would otherwise block its own first deposit, same bug
            // this module hit originally with the control GL. See the "Teller Cash Deposit
            // / Withdrawal" plan for the full reasoning.
            $customerCashGeneralLedger = $this->generalLedgers->create([
                'name'                            => "Teller Cash Suspense — {$data['name']}",
                'type'                            => 'teller_cash_suspense',
                'classification'                  => GeneralLedgerClassification::EXPENSE->value,
                'currency_id'                     => (int) $data['currency_id'],
                // Unlike the control GL, this one is never swept by a posting (see
                // CashierSessionService::sweep()'s doc comment) — it accumulates as a
                // running total that back-office ops periodically clears via a manual
                // journal entry, hence true here.
                'manual_journal_entries_allowed'  => true,
                'status'                           => GeneralLedgerStatus::ACTIVE->value,
                'is_assigned'                      => true,
                'description'                      => "Customer deposit/withdrawal clearing GL for teller '{$data['name']}'.",
            ]);

            return Teller::create([
                'branch_id'                          => $data['branch_id'],
                'vault_id'                           => $vault->id,
                'general_ledger_id'                  => $generalLedger->id,
                'customer_cash_general_ledger_id'    => $customerCashGeneralLedger->id,
                'name'                                => $data['name'],
                'code'                                => $this->generateTellerCode($branch),
                'currency_id'                         => $data['currency_id'],
                'opening_float_ceiling_amount'        => $data['opening_float_ceiling_amount'] ?? null,
                'variance_tolerance_amount'           => $data['variance_tolerance_amount'] ?? 0,
                'requires_variance_approval'          => $data['requires_variance_approval'] ?? true,
                'allows_teller_to_teller_transfer'    => $data['allows_teller_to_teller_transfer'] ?? false,
                'created_by'                          => Auth::guard('user')->id(),
                'status'                              => TellerStatus::PENDING->value,
            ])->refresh();
        });

        $this->audit->log(
            action: 'created',
            module: 'tellers',
            auditable: $teller,
            after: $teller->toArray(),
            description: "Teller '{$teller->name}' ({$teller->code}) was created by '{$actor?->username}'.",
        );

        return $teller->load(['branch', 'vault', 'currency', 'generalLedger', 'customerCashGeneralLedger']);
    }

    public function update(Teller $teller, array $data): Teller
    {
        $before = $teller->toArray();
        $actor  = Auth::guard('user')->user();

        unset($data['branch_id'], $data['vault_id'], $data['currency_id'], $data['status']);

        $teller->update($data);
        $teller->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'tellers',
            auditable: $teller,
            before: $before,
            after: $teller->toArray(),
            description: "Teller '{$teller->name}' ({$teller->code}) was updated by '{$actor?->username}'.",
        );

        return $teller;
    }

    public function approve(Teller $teller): Teller
    {
        if ($teller->status !== TellerStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'status' => ['Only pending tellers can be approved.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor && (int) $teller->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a teller you created yourself.'],
            ]);
        }

        $before = $teller->status;

        $teller->update([
            'status'      => TellerStatus::ACTIVE->value,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ]);
        $teller->refresh();

        $this->audit->log(
            action: 'approved',
            module: 'tellers',
            auditable: $teller,
            before: ['status' => $before],
            after: ['status' => $teller->status],
            description: "Teller '{$teller->name}' ({$teller->code}) was approved by '{$actor?->username}'.",
        );

        return $teller;
    }

    public function updateStatus(Teller $teller, string $status): Teller
    {
        $before = $teller->status;
        $actor  = Auth::guard('user')->user();

        $teller->update(['status' => $status]);
        $teller->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'tellers',
            auditable: $teller,
            before: ['status' => $before],
            after: ['status' => $teller->status],
            description: "Teller '{$teller->name}' ({$teller->code}) status changed from '{$before}' to '{$teller->status}' by '{$actor?->username}'.",
        );

        return $teller;
    }

    // Assigns a cashier to a teller. A teller may only have one active assignment, and a
    // cashier may only hold one active assignment, at any time — both checked under a
    // locked transaction (mirrors PostingService::approve()'s locked re-check pattern) to
    // close the race window between two concurrent assignment requests. Reassignment
    // additionally requires the teller to have no open session, so cash never gets handed
    // over blind.
    public function assign(Teller $teller, int $userId): CashierAssignment
    {
        $actor = Auth::guard('user')->user();

        $assignment = DB::transaction(function () use ($teller, $userId) {
            $lockedTeller = Teller::whereKey($teller->id)->lockForUpdate()->firstOrFail();

            if ($lockedTeller->status !== TellerStatus::ACTIVE->value) {
                throw ValidationException::withMessages([
                    'teller_id' => ['Only an active teller can be assigned.'],
                ]);
            }

            if (CashierAssignment::where('teller_id', $lockedTeller->id)->whereNull('unassigned_at')->exists()) {
                throw ValidationException::withMessages([
                    'teller_id' => ['This teller already has an active assignment — unassign it first.'],
                ]);
            }

            if ($lockedTeller->currentSession) {
                throw ValidationException::withMessages([
                    'teller_id' => ['This teller has an open session — close it before reassigning.'],
                ]);
            }

            if (CashierAssignment::where('user_id', $userId)->whereNull('unassigned_at')->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => ['This user is already assigned to another teller — unassign them first.'],
                ]);
            }

            return CashierAssignment::create([
                'teller_id'   => $lockedTeller->id,
                'user_id'     => $userId,
                'assigned_by' => Auth::guard('user')->id(),
                'assigned_at' => now(),
            ])->refresh();
        });

        $this->audit->log(
            action: 'assigned',
            module: 'tellers',
            auditable: $teller,
            after: ['user_id' => $userId],
            description: "Teller '{$teller->name}' ({$teller->code}) was assigned to user #{$userId} by '{$actor?->username}'.",
        );

        return $assignment->load('user');
    }

    // Ends a teller's active assignment. Blocked while a session is open — the current
    // cashier must close their teller first.
    public function unassign(Teller $teller): Teller
    {
        $actor = Auth::guard('user')->user();

        DB::transaction(function () use ($teller) {
            $lockedTeller = Teller::whereKey($teller->id)->lockForUpdate()->firstOrFail();

            if ($lockedTeller->currentSession()->exists()) {
                throw ValidationException::withMessages([
                    'teller_id' => ['This teller has an open session — close it before unassigning.'],
                ]);
            }

            $assignment = CashierAssignment::where('teller_id', $lockedTeller->id)->whereNull('unassigned_at')->first();

            if (! $assignment) {
                throw ValidationException::withMessages([
                    'teller_id' => ['This teller has no active assignment.'],
                ]);
            }

            $assignment->update([
                'unassigned_by' => Auth::guard('user')->id(),
                'unassigned_at' => now(),
            ]);
        });

        $this->audit->log(
            action: 'unassigned',
            module: 'tellers',
            auditable: $teller,
            description: "Teller '{$teller->name}' ({$teller->code}) was unassigned by '{$actor?->username}'.",
        );

        return $teller->refresh();
    }

    // TLR-{branch_code}-01, sequential per branch.
    private function generateTellerCode(Branch $branch): string
    {
        $prefix = "TLR-{$branch->code}-";

        $lastSequence = Teller::where('code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = $lastSequence ? ((int) substr($lastSequence, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
    }
}
