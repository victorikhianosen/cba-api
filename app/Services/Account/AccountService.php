<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\Customer\CustomerType;
use App\Models\Account;
use App\Models\AccountProduct;
use App\Models\AccountTier;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    // Account product each customer_type is defaulted into on approval.
    private const PRODUCT_TYPE_BY_CUSTOMER_TYPE = [
        'individual' => 'savings',
        'minor'      => 'savings',
        'business'   => 'current',
        'government' => 'current',
        'corporate'  => 'current',
    ];

    // Leading digit of the 10-digit account number, by product type — savings run 1000000001+, current run 2000000001+, domiciliary (dollar) run 3000000001+.
    private const ACCOUNT_NUMBER_PREFIX_BY_PRODUCT_TYPE = [
        'savings'     => '1',
        'current'     => '2',
        'domiciliary' => '3',
    ];

    public function __construct(
        private AuditService $audit,
    ) {}

    public function find(int $id): Account
    {
        return Account::with(['customer', 'accountProduct', 'currency', 'accountTier'])->findOrFail($id);
    }

    // $search matches the account number/name directly, or the owning customer's cif number/name.
    public function list(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Account::query()
            ->with(['customer', 'accountProduct', 'currency', 'accountTier'])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('account_number', 'like', "%{$search}%")
                        ->orWhere('account_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query->where('cif_number', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('business_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function transactionsFor(int $accountId, int $perPage = 20): LengthAwarePaginator
    {
        return Transaction::where('account_id', $accountId)
            ->latest('posting_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    // Lets an already-onboarded customer open an additional account; the requested type must match the product's own type. One transaction — if anything fails, nothing is created.
    public function openAccountForCustomer(Customer $customer, array $data): Account
    {
        $account = $this->transactionWithAccountNumberRetry(function () use ($customer, $data) {
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

            $actor = Auth::guard('user')->user();

            return Account::create([
                'customer_id'        => $customer->id,
                'account_product_id' => $accountProduct->id,
                'currency_id'        => $accountProduct->currency_id,
                'account_tier_id'    => $accountTier?->id,
                'account_name'       => $this->accountName($customer),
                'account_number'     => $this->generateUniqueAccountNumber($accountProduct->product_type),
                'primary_account'    => ! $customer->accounts()->exists(),
                'status'             => 'active',
                'created_by'         => $actor?->id,
            ])->refresh();
        });

        $actor = Auth::guard('user')->user();

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

    // Cascades a status change to every account belonging to a customer, in one transaction — if any account fails to update, none of them do.
    public function updateStatusForCustomer(Customer $customer, string $status): Collection
    {
        return DB::transaction(
            fn () => $customer->accounts()->get()->map(fn (Account $account) => $this->applyStatus($account, $status)),
        );
    }

    private function applyStatus(Account $account, string $status): Account
    {
        $before = $account->status;
        $actor  = Auth::guard('user')->user();

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
        $actor  = Auth::guard('user')->user();

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

        $account = $this->transactionWithAccountNumberRetry(function () use ($customer, $accountProduct) {
            $accountTier = AccountTier::where('level', 1)->where('status', 'active')->first();
            $actor       = Auth::guard('user')->user();

            return Account::create([
                'customer_id'        => $customer->id,
                'account_product_id' => $accountProduct->id,
                'currency_id'        => $accountProduct->currency_id,
                'account_tier_id'    => $accountTier?->id,
                'account_name'       => $this->accountName($customer),
                'account_number'     => $this->generateUniqueAccountNumber($accountProduct->product_type),
                'primary_account'    => true,
                'status'             => 'active',
                'created_by'         => $actor?->id,
            ])->refresh();
        });

        $actor = Auth::guard('user')->user();

        $this->audit->log(
            action: 'opened',
            module: 'accounts',
            auditable: $account,
            after: $account->toArray(),
            description: "Account '{$account->account_number}' ({$accountProduct->name}) was opened for customer '{$customer->cif_number}' on tier {$account->accountTier?->level} by '{$actor?->username}'.",
        );

        return $account;
    }

    // Individuals carry their own BVN. Business/government have no directors — theirs lives on the
    // signatory. Corporate customers have both — either a director or a signatory with a BVN qualifies.
    private function isEligibleForAutoOpening(Customer $customer): bool
    {
        $hasBvn = match (true) {
            $customer->customer_type === CustomerType::CORPORATE->value => $customer->directors()->whereNotNull('bvn')->exists()
                || $customer->signatories()->whereNotNull('bvn')->exists(),
            in_array($customer->customer_type, CustomerType::businessLike(), true) => $customer->signatories()->whereNotNull('bvn')->exists(),
            default => ! empty($customer->bvn),
        };

        return $hasBvn && $customer->documents()->where('type', 'profile_picture')->exists();
    }

    private function accountName(Customer $customer): string
    {
        return in_array($customer->customer_type, CustomerType::businessLike(), true)
            ? (string) $customer->business_name
            : trim("{$customer->first_name} {$customer->last_name}");
    }

    // generateUniqueAccountNumber()'s lockForUpdate() has nothing to lock when a product-type
    // prefix has zero existing accounts, so two concurrent "first account under this prefix"
    // requests can compute the same number. The account_number column is DB-unique, so that
    // collision surfaces as a QueryException at INSERT rather than a silent duplicate — this
    // retries account creation a few times on that specific error instead of failing the request.
    private function transactionWithAccountNumberRetry(callable $callback): Account
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $e) {
                $attempts++;

                if ($attempts >= 3 || $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
    }

    // Account numbers are sequential per product type, not random. Must be called inside a DB::transaction so lockForUpdate() actually holds the lock until commit.
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
