<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\Account\AccountStatus;
use App\Enums\Product\AccountProductType;
use App\Enums\Transaction\FinancialTransactionStatus;
use App\Models\Account;
use App\Models\AccountProduct;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\VirtualAccountDetail;
use App\Services\Audit\AuditService;
use App\Services\GeneralLedger\GeneralLedgerPostingService;
use App\Services\Notification\NotificationService;
use App\Support\ReferenceGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Virtual accounts are pure collection endpoints (Paystack/Monnify Dedicated Virtual
// Account model) — every credit sweeps straight through to a real parent settlement
// account; the virtual account's own accounts.account_balance always stays ₦0.
class VirtualAccountService
{
    public function __construct(
        private AccountService $accounts,
        private AccountPostingService $accountPosting,
        private GeneralLedgerPostingService $glLedger,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?int $customerId = null, ?int $parentAccountId = null, ?string $search = null): LengthAwarePaginator
    {
        return Account::query()
            ->whereHas('accountProduct', fn ($query) => $query->where('product_type', 'virtual'))
            ->with(['customer', 'accountProduct', 'currency', 'virtualAccountDetails.parentAccount'])
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($parentAccountId, fn ($query) => $query->whereHas(
                'virtualAccountDetails',
                fn ($query) => $query->where('parent_account_id', $parentAccountId),
            ))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('account_number', 'like', "%{$search}%")
                        ->orWhere('account_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Account
    {
        return Account::query()
            ->whereHas('accountProduct', fn ($query) => $query->where('product_type', 'virtual'))
            ->with(['customer', 'accountProduct', 'currency', 'virtualAccountDetails.parentAccount'])
            ->findOrFail($id);
    }

    public function create(Customer $customer, array $data): Account
    {
        $account = DB::transaction(function () use ($customer, $data) {
            $parentAccount = Account::with('accountProduct')->findOrFail($data['parent_account_id']);

            if ((int) $parentAccount->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'parent_account_id' => ['The parent account does not belong to this customer.'],
                ]);
            }

            if ($parentAccount->status !== AccountStatus::ACTIVE->value) {
                throw ValidationException::withMessages([
                    'parent_account_id' => ["Parent account '{$parentAccount->account_number}' is not active."],
                ]);
            }

            if ($parentAccount->accountProduct?->product_type === 'virtual') {
                throw ValidationException::withMessages([
                    'parent_account_id' => ['A virtual account cannot itself be the parent of another virtual account.'],
                ]);
            }

            $accountProduct = AccountProduct::findOrFail($data['account_product_id']);

            if ($accountProduct->product_type !== 'virtual') {
                throw ValidationException::withMessages([
                    'account_product_id' => ["Account product '{$accountProduct->name}' is not a virtual account product."],
                ]);
            }

            if ($accountProduct->status !== 'active') {
                throw ValidationException::withMessages([
                    'account_product_id' => ["Account product '{$accountProduct->name}' is not active."],
                ]);
            }

            if ((int) $accountProduct->currency_id !== (int) $parentAccount->currency_id) {
                throw ValidationException::withMessages([
                    'account_product_id' => ['The virtual account product currency must match the parent account currency.'],
                ]);
            }

            $virtualAccount = $this->accounts->openVirtualAccountForCustomer(
                $customer,
                $accountProduct,
                $parentAccount,
                $data['beneficiary_name'],
            );

            VirtualAccountDetail::create([
                'account_id'        => $virtualAccount->id,
                'parent_account_id' => $parentAccount->id,
                'beneficiary_name'  => $data['beneficiary_name'],
                'beneficiary_email' => $customer->email,
                'beneficiary_phone' => $customer->phone,
                'beneficiary_bvn'   => $customer->bvn,
                'purpose'           => $data['purpose'] ?? null,
                'reference'         => ReferenceGenerator::generate('VA'),
                'provider'          => 'internal',
                'expires_at'        => $data['expires_at'] ?? null,
            ]);

            return $virtualAccount;
        });

        $actor = Auth::guard('user')->user();

        $this->audit->log(
            action: 'opened',
            module: 'virtual_accounts',
            auditable: $account,
            after: $account->toArray(),
            description: "Virtual account '{$account->account_number}' was opened for customer '{$customer->cif_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'create_virtual_accounts',
            'virtual_account',
            $account->id,
            'New virtual account opened',
            "Virtual account '{$account->account_number}' was opened for customer '{$customer->cif_number}'.",
        );

        return $this->find($account->id);
    }

    // The only field on a virtual account that's ever independently editable — everything
    // else (email/phone/BVN) mirrors the parent customer's real KYC and is never re-set here.
    public function updateBeneficiaryName(Account $virtualAccount, string $beneficiaryName): Account
    {
        $details = $virtualAccount->virtualAccountDetails()->with('parentAccount')->firstOrFail();
        $before  = $details->beneficiary_name;
        $actor   = Auth::guard('user')->user();

        $details->update(['beneficiary_name' => $beneficiaryName]);
        $virtualAccount->update([
            'account_name' => $this->accounts->virtualAccountName($details->parentAccount, $beneficiaryName),
        ]);

        $this->audit->log(
            action: 'updated',
            module: 'virtual_accounts',
            auditable: $virtualAccount,
            before: ['beneficiary_name' => $before],
            after: ['beneficiary_name' => $beneficiaryName],
            description: "Virtual account '{$virtualAccount->account_number}' beneficiary name changed from '{$before}' to '{$beneficiaryName}' by '{$actor?->username}'.",
        );

        return $this->find($virtualAccount->id);
    }

    public function updateStatus(Account $virtualAccount, string $status): Account
    {
        $before = $virtualAccount->status;
        $actor  = Auth::guard('user')->user();

        $virtualAccount->update(['status' => $status]);

        $this->audit->log(
            action: 'status_updated',
            module: 'virtual_accounts',
            auditable: $virtualAccount,
            before: ['status' => $before],
            after: ['status' => $status],
            description: "Virtual account '{$virtualAccount->account_number}' status changed from '{$before}' to '{$status}' by '{$actor?->username}'.",
        );

        return $this->find($virtualAccount->id);
    }

    // Internal test/demo scaffolding only — this phase has no real NUBAN provider webhook
    // to source an inbound payment from, so this is the only way to exercise the sweep.
    // Two balanced legs under one FinancialTransaction: the Virtual Account Collections GL
    // (liability) is credited for the incoming funds, then immediately debited back down as
    // the parent account (and its own ACCOUNT_CONTROL GL) is credited by the same amount —
    // net effect: money "arrives", is instantly swept, VA's own balance never moves.
    public function creditFromInboundPayment(Account $virtualAccount, string $amount, ?string $narration = null, ?string $providerReference = null): FinancialTransaction
    {
        return DB::transaction(function () use ($virtualAccount, $amount, $narration, $providerReference) {
            $virtualAccount = Account::with('accountProduct.generalLedgerMappings.generalLedger', 'virtualAccountDetails.parentAccount.accountProduct')
                ->lockForUpdate()
                ->findOrFail($virtualAccount->id);

            if ($virtualAccount->status !== AccountStatus::ACTIVE->value) {
                throw ValidationException::withMessages([
                    'virtual_account' => ["Virtual account '{$virtualAccount->account_number}' is not active."],
                ]);
            }

            $details       = $virtualAccount->virtualAccountDetails;
            $parentAccount = $details->parentAccount;

            $collectionsGl = $virtualAccount->accountProduct
                ->generalLedgerMappings
                ->firstWhere('financial_account_type', AccountProductType::VIRTUAL_ACCOUNT_COLLECTIONS->value)
                ?->generalLedger;

            if (! $collectionsGl) {
                throw ValidationException::withMessages([
                    'virtual_account' => ["Virtual account product has no Virtual Account Collections general ledger mapped."],
                ]);
            }

            $actor = Auth::guard('user')->user();

            $financialTransaction = FinancialTransaction::create([
                'reference'             => ReferenceGenerator::generate('FRN'),
                'transaction_reference' => ReferenceGenerator::generate('TRN'),
                'idempotency_key'       => (string) Str::uuid(),
                'module'                => 'virtual_accounts',
                'transaction_type'      => 'virtual_account_sweep',
                'business_service'      => 'virtual_accounts',
                'channel'               => 'internal_simulation',
                'customer_id'           => $parentAccount->customer_id,
                'amount'                => $amount,
                'fee'                   => 0,
                'commission'            => 0,
                'vat'                   => 0,
                'discount'              => 0,
                'currency_id'           => $virtualAccount->currency_id,
                'exchange_rate'         => 1,
                'status'                => FinancialTransactionStatus::COMPLETED->value,
                'narration'             => $narration,
                'created_by'            => $actor?->id,
                'processed_at'          => now(),
                'completed_at'          => now(),
            ]);

            $leg = [
                'financial_transaction_id' => $financialTransaction->id,
                'entry_group_id'           => (string) Str::uuid(),
                'transaction_reference'    => $financialTransaction->transaction_reference,
                'amount'                   => $amount,
                'currency_id'              => $virtualAccount->currency_id,
                'narration'                => $narration ?? "Virtual account {$virtualAccount->account_number} collection swept to {$parentAccount->account_number}",
                'created_by'               => $actor?->id,
                'module'                   => 'virtual_accounts',
                'transaction_type'         => 'virtual_account_sweep',
                'channel'                  => 'internal_simulation',
                'provider_reference'       => $providerReference,
                'counterparty_type'           => 'account',
                'counterparty_id'             => $virtualAccount->id,
                'counterparty_name'           => $details->beneficiary_name,
                'counterparty_account_number' => $virtualAccount->account_number,
            ];

            // 1. Funds "land" in the collections GL.
            $this->glLedger->credit($collectionsGl, $leg);
            // 2. Immediately sweep: collections GL discharged, parent account (and its
            //    ACCOUNT_CONTROL GL, mirrored inside credit()) receives the real funds.
            $this->glLedger->debit($collectionsGl, $leg);
            $transaction = $this->accountPosting->credit($parentAccount, $leg);

            $this->audit->log(
                action: 'swept',
                module: 'virtual_accounts',
                auditable: $virtualAccount,
                after: [
                    'parent_account_id' => $parentAccount->id,
                    'amount'            => $amount,
                    'transaction_id'    => $transaction->id,
                ],
                description: "Virtual account '{$virtualAccount->account_number}' received {$amount} and swept it to parent account '{$parentAccount->account_number}'.",
            );

            $this->notifications->notifyPermission(
                'view_virtual_accounts',
                'virtual_account',
                $virtualAccount->id,
                'Virtual account credited',
                "Virtual account '{$virtualAccount->account_number}' received {$amount} — swept to '{$parentAccount->account_number}'.",
            );

            return $financialTransaction->load('transactions', 'journalEntries');
        });
    }
}
