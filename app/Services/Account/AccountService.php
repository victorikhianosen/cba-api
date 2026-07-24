<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Account;
use App\Models\AccountProduct;
use App\Models\AccountTier;
use App\Models\Customer;
use App\Services\Audit\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * Account product each customer_type is defaulted into on approval.
     */
    private const PRODUCT_TYPE_BY_CUSTOMER_TYPE = [
        'individual' => 'savings',
        'business'   => 'current',
    ];

    /**
     * Leading digit of the 10-digit account number, by product type —
     * savings accounts run 1000000001, 1000000002, ...; current accounts
     * run 2000000001, 2000000002, ...; and so on per product type.
     */
    private const ACCOUNT_NUMBER_PREFIX_BY_PRODUCT_TYPE = [
        'savings'     => '1',
        'current'     => '2',
        'merchant'    => '3',
        'corporate'   => '4',
        'domiciliary' => '5',
    ];

    public function __construct(
        private AuditService $audit,
    ) {}

    public function find(int $id): Account
    {
        return Account::with(['customer', 'accountProduct', 'currency', 'accountTier'])->findOrFail($id);
    }

    /**
     * Lets an already-onboarded customer open an additional account. Only
     * the account product, the requested account type (savings/current),
     * and optionally the account tier are accepted — the requested type
     * must match the product's own type, so a "current" request can only be
     * fulfilled by a "current" product. When no tier is given, the lowest
     * active tier is used, same as the default account opened on approval.
     * The whole operation is one transaction: if anything fails, nothing is
     * created.
     */
    public function openAccountForCustomer(Customer $customer, array $data): Account
    {
        $account = DB::transaction(function () use ($customer, $data) {
            $accountProduct = AccountProduct::findOrFail($data['account_product_id']);

            if ($accountProduct->status !== 'active') {
                throw ValidationException::withMessages([
                    'account_product_id' => ["Account product '{$accountProduct->name}' is not active."],
                ]);
            }

            if ($accountProduct->product_type !== $data['account_type']) {
                throw ValidationException::withMessages([
                    'account_product_id' => ["Account product '{$accountProduct->name}' is a '{$accountProduct->product_type}' product and cannot be used to open a '{$data['account_type']}' account."],
                ]);
            }

            if (! empty($data['account_tier_id'])) {
                $accountTier = AccountTier::findOrFail($data['account_tier_id']);

                if ($accountTier->status !== 'active') {
                    throw ValidationException::withMessages([
                        'account_tier_id' => ["Account tier (level {$accountTier->level}) is not active."],
                    ]);
                }
            } else {
                $accountTier = AccountTier::where('level', 1)->where('status', 'active')->first();
            }

            $actor = auth()->user();

            return Account::create([
                'customer_id'        => $customer->id,
                'account_product_id' => $accountProduct->id,
                'currency_id'        => $accountProduct->currency_id,
                'account_tier_id'    => $accountTier?->id,
                'account_name'       => $this->accountName($customer),
                'account_number'     => $this->generateUniqueAccountNumber($accountProduct->product_type),
                'status'             => 'active',
                'created_by'         => $actor?->id,
            ])->refresh();
        });

        $actor = auth()->user();

        $this->audit->log(
            action: 'opened',
            module: 'accounts',
            auditable: $account,
            after: $account->toArray(),
            description: "Account '{$account->account_number}' ({$account->accountProduct?->name}) was opened for customer '{$customer->cif_number}' by '{$actor?->username}'.",
        );

        return $account->load(['accountProduct', 'currency', 'accountTier']);
    }

    public function updateStatus(int $id, string $status): Account
    {
        return $this->applyStatus(Account::findOrFail($id), $status);
    }

    /**
     * Cascades a status change to every account belonging to a customer —
     * only the accounts change, the customer record itself is untouched.
     * Wrapped in a single transaction: if any account fails to update, none
     * of them do.
     */
    public function updateStatusForCustomer(Customer $customer, string $status): Collection
    {
        return DB::transaction(
            fn () => $customer->accounts()->get()->map(fn (Account $account) => $this->applyStatus($account, $status)),
        );
    }

    private function applyStatus(Account $account, string $status): Account
    {
        $before = $account->status;
        $actor  = auth()->user();

        $account->update(['status' => $status]);
        $account->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'accounts',
            auditable: $account,
            before: ['status' => $before],
            after: ['status' => $account->status],
            description: "Account '{$account->account_number}' status changed from '{$before}' to '{$account->status}' by '{$actor?->username}'.",
        );

        return $account;
    }

    public function togglePnd(int $id): Account
    {
        return $this->toggleFlag(Account::findOrFail($id), 'pnd', 'PND (post no debit)');
    }

    public function togglePnc(int $id): Account
    {
        return $this->toggleFlag(Account::findOrFail($id), 'pnc', 'PNC (post no credit)');
    }

    private function toggleFlag(Account $account, string $field, string $label): Account
    {
        $before = (bool) $account->{$field};
        $actor  = auth()->user();

        $account->update([$field => ! $before]);
        $account->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'accounts',
            auditable: $account,
            before: [$field => $before],
            after: [$field => $account->{$field}],
            description: "Account '{$account->account_number}' {$label} flag was " . ($account->{$field} ? 'enabled' : 'disabled') . " by '{$actor?->username}'.",
        );

        return $account;
    }

    public function openDefaultAccountForCustomer(Customer $customer): ?Account
    {
        if (! $this->isEligibleForAutoOpening($customer) || $customer->accounts()->exists()) {
            return null;
        }

        $productType = self::PRODUCT_TYPE_BY_CUSTOMER_TYPE[$customer->customer_type] ?? 'savings';

        $accountProduct = AccountProduct::where('product_type', $productType)
            ->where('status', 'active')
            ->first();

        if (! $accountProduct) {
            return null;
        }

        $account = DB::transaction(function () use ($customer, $accountProduct) {
            $accountTier = AccountTier::where('level', 1)->where('status', 'active')->first();
            $actor       = auth()->user();

            return Account::create([
                'customer_id'        => $customer->id,
                'account_product_id' => $accountProduct->id,
                'currency_id'        => $accountProduct->currency_id,
                'account_tier_id'    => $accountTier?->id,
                'account_name'       => $this->accountName($customer),
                'account_number'     => $this->generateUniqueAccountNumber($accountProduct->product_type),
                'status'             => 'active',
                'created_by'         => $actor?->id,
            ])->refresh();
        });

        $actor = auth()->user();

        $this->audit->log(
            action: 'opened',
            module: 'accounts',
            auditable: $account,
            after: $account->toArray(),
            description: "Account '{$account->account_number}' ({$accountProduct->name}) was opened for customer '{$customer->cif_number}' on tier {$account->accountTier?->level} by '{$actor?->username}'.",
        );

        return $account;
    }

    /**
     * Individuals carry their own BVN; business customers don't — theirs
     * lives on the director record instead, so a business is eligible once
     * any director has a BVN on file.
     */
    private function isEligibleForAutoOpening(Customer $customer): bool
    {
        $hasBvn = $customer->customer_type === 'business'
            ? $customer->directors()->whereNotNull('bvn')->exists()
            : ! empty($customer->bvn);

        return $hasBvn && $customer->documents()->where('type', 'profile_picture')->exists();
    }

    private function accountName(Customer $customer): string
    {
        return $customer->customer_type === 'business'
            ? (string) $customer->business_name
            : trim("{$customer->first_name} {$customer->last_name}");
    }

    /**
     * Account numbers are sequential per product type, not random: savings
     * accounts run 1000000001, 1000000002, ...; current accounts run
     * 2000000001, 2000000002, .... Must be called inside a DB::transaction
     * so lockForUpdate() actually holds the row lock until commit.
     */
    private function generateUniqueAccountNumber(string $productType): string
    {
        $prefix = self::ACCOUNT_NUMBER_PREFIX_BY_PRODUCT_TYPE[$productType] ?? '9';

        $lastNumber = Account::where('account_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('account_number')
            ->value('account_number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, 1)) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 9, '0', STR_PAD_LEFT);
    }
}
