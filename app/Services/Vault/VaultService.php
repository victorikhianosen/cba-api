<?php

declare(strict_types=1);

namespace App\Services\Vault;

use App\Enums\GeneralLedger\GeneralLedgerClassification;
use App\Enums\GeneralLedger\GeneralLedgerStatus;
use App\Enums\Vault\VaultStatus;
use App\Models\Branch;
use App\Models\Vault;
use App\Services\Audit\AuditService;
use App\Services\GeneralLedger\GeneralLedgerService;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VaultService
{
    public function __construct(
        private AuditService $audit,
        private GeneralLedgerService $generalLedgers,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Vault::query()
            ->with(['branch', 'currency', 'generalLedger'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Vault
    {
        return Vault::with(['branch', 'currency', 'generalLedger', 'createdBy', 'approvedBy'])->findOrFail($id);
    }

    // Creates the vault's own cash-asset GL alongside it — a vault always needs exactly one
    // GL role, so there's no separate "pick a GL" step the way AccountProduct/InvestmentProduct
    // need for their several named roles.
    public function create(array $data): Vault
    {
        $actor = Auth::guard('user')->user();

        $vault = DB::transaction(function () use ($data) {
            $branch = Branch::findOrFail($data['branch_id']);

            // Classified LIABILITY, not ASSET, mirroring how ACCOUNT_CONTROL GLs (which
            // mirror customer account balances) are classified in this codebase — see
            // app/Services/GeneralLedger/GeneralLedgerPostingService.php's
            // NORMAL_DEBIT_CLASSIFICATIONS. A "control" GL that a posting's source-side
            // balance check (hasSufficientBalance()) needs to correctly gate as "the giving
            // party can't move out more than it holds" must decrease on debit, which is
            // liability polarity, not asset polarity (asset increases on debit). Every
            // vault/till cash movement in this module treats whichever party is giving cash
            // as the posting source (debited, decreases) and the receiver as destination
            // (credited, increases) — see TillSessionService.
            $generalLedger = $this->generalLedgers->create([
                'name'                           => "Vault Cash — {$data['name']}",
                'type'                           => 'vault_cash',
                'classification'                 => GeneralLedgerClassification::LIABILITY->value,
                'currency_id'                    => (int) $data['currency_id'],
                'manual_journal_entries_allowed'  => false,
                'status'                          => GeneralLedgerStatus::ACTIVE->value,
                'is_assigned'                     => true,
                'description'                     => "Cash reserve GL for vault '{$data['name']}' ({$branch->name}).",
            ]);

            return Vault::create([
                'branch_id'         => $data['branch_id'],
                'general_ledger_id' => $generalLedger->id,
                'name'              => $data['name'],
                'code'              => $this->generateVaultCode($branch),
                'currency_id'       => $data['currency_id'],
                'created_by'        => Auth::guard('user')->id(),
                'status'            => VaultStatus::PENDING->value,
            ])->refresh();
        });

        $this->audit->log(
            action: 'created',
            module: 'vaults',
            auditable: $vault,
            after: $vault->toArray(),
            description: "Vault '{$vault->name}' ({$vault->code}) was created by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_vaults',
            'vaults',
            $vault->id,
            'New vault request',
            "Vault '{$vault->name}' ({$vault->code}) is awaiting your approval.",
        );

        return $vault->load(['branch', 'currency', 'generalLedger']);
    }

    public function update(Vault $vault, array $data): Vault
    {
        $before = $vault->toArray();
        $actor  = Auth::guard('user')->user();

        unset($data['branch_id'], $data['currency_id'], $data['status']);

        $vault->update($data);
        $vault->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'vaults',
            auditable: $vault,
            before: $before,
            after: $vault->toArray(),
            description: "Vault '{$vault->name}' ({$vault->code}) was updated by '{$actor?->username}'.",
        );

        return $vault;
    }

    public function approve(Vault $vault): Vault
    {
        if ($vault->status !== VaultStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'status' => ['Only pending vaults can be approved.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor && (int) $vault->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a vault you created yourself.'],
            ]);
        }

        $before = $vault->status;

        $vault->update([
            'status'      => VaultStatus::ACTIVE->value,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ]);
        $vault->refresh();

        $this->audit->log(
            action: 'approved',
            module: 'vaults',
            auditable: $vault,
            before: ['status' => $before],
            after: ['status' => $vault->status],
            description: "Vault '{$vault->name}' ({$vault->code}) was approved by '{$actor?->username}'.",
        );

        return $vault;
    }

    public function updateStatus(Vault $vault, string $status): Vault
    {
        $before = $vault->status;
        $actor  = Auth::guard('user')->user();

        $vault->update(['status' => $status]);
        $vault->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'vaults',
            auditable: $vault,
            before: ['status' => $before],
            after: ['status' => $vault->status],
            description: "Vault '{$vault->name}' ({$vault->code}) status changed from '{$before}' to '{$vault->status}' by '{$actor?->username}'.",
        );

        return $vault;
    }

    // VLT-{branch_code}-001, sequential per branch — mirrors how account numbers are
    // generated sequentially per product type in AccountService::generateUniqueAccountNumber().
    private function generateVaultCode(Branch $branch): string
    {
        $prefix = "VLT-{$branch->code}-";

        $lastSequence = Vault::where('code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = $lastSequence ? ((int) substr($lastSequence, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
