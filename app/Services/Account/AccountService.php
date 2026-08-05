<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\Customer\CustomerType;
use App\Mail\CoreBankMail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Account;
use App\Models\AccountProduct;
use App\Models\AccountTier;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AccountService
{
    // Account product each customer_type is defaulted into on approval.
    // 'minor' is not a separate customer_type — a minor is just an individual with
    // guardian_id set (see StoreCustomerRequest), so it needs no entry of its own here.
    private const PRODUCT_TYPE_BY_CUSTOMER_TYPE = [
        'individual' => 'savings',
        'business'   => 'current',
    ];

    // Leading digit of the 10-digit account number, by product type — savings run 1000000001+, current run 2000000001+, domiciliary (dollar) run 3000000001+.
    private const ACCOUNT_NUMBER_PREFIX_BY_PRODUCT_TYPE = [
        'savings'     => '1',
        'current'     => '2',
        'domiciliary' => '3',
    ];

    public function __construct(
        private AuditService $audit,
        private NotificationService $notifications,
        private AccountStatementTemplateService $statementTemplates,
        private FileStorageService $files,
    ) {}

    public function find(int $id): Account
    {
        return Account::with(['customer', 'accountProduct', 'currency', 'accountTier'])->findOrFail($id);
    }

    public function findByAccountNumber(string $accountNumber): ?Account
    {
        return Account::with(['customer', 'accountProduct', 'currency'])
            ->where('account_number', $accountNumber)
            ->first();
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

    // Builds a real bank statement of account: opening balance carried forward from
    // whatever precedes the period, a running balance per row, and debit/credit split
    // out of the single signed `amount` column (direction/type tell us which side).
    public function statementData(Account $account, ?string $from, ?string $to): array
    {
        $account->loadMissing(['customer', 'accountProduct', 'currency']);

        $to   = $to ?: now()->toDateString();
        $from = $from ?: $account->created_at?->toDateString() ?? $to;

        $openingBalance = (float) (
            Transaction::where('account_id', $account->id)
                ->whereDate('posting_date', '<', $from)
                ->orderByDesc('posting_date')
                ->orderByDesc('id')
                ->value('balance_after')
            ?? $account->accountProduct?->min_required_opening_balance
            ?? 0
        );

        $transactions = Transaction::where('account_id', $account->id)
            ->whereDate('posting_date', '>=', $from)
            ->whereDate('posting_date', '<=', $to)
            ->orderBy('posting_date')
            ->orderBy('id')
            ->get();

        $runningBalance = $openingBalance;
        $totalDebit     = 0.0;
        $totalCredit    = 0.0;

        $rows = $transactions->map(function (Transaction $transaction) use (&$runningBalance, &$totalDebit, &$totalCredit) {
            $isDebit = $transaction->type === 'debit';
            $amount  = (float) $transaction->amount;

            $runningBalance = $isDebit ? $runningBalance - $amount : $runningBalance + $amount;

            if ($isDebit) {
                $totalDebit += $amount;
            } else {
                $totalCredit += $amount;
            }

            return [
                'date'      => $transaction->posting_date?->format('d M Y'),
                'reference' => $transaction->reference ?? $transaction->transaction_reference,
                'narration' => $transaction->narration ?: ucfirst((string) $transaction->transaction_type),
                'debit'     => $isDebit ? number_format($amount, 2) : null,
                'credit'    => $isDebit ? null : number_format($amount, 2),
                'balance'   => number_format($runningBalance, 2),
            ];
        });

        $template = $this->statementTemplates->getDefault();
        $customer = $account->customer;

        return [
            'companyName'     => config('app.name'),
            'headerImageBase64' => $this->imageDataUri($template?->header_image),
            'generatedAt'     => now()->format('F j, Y g:i A'),
            'accountName'     => $account->account_name,
            'accountNumber'   => $account->account_number,
            'accountProduct'  => $account->accountProduct?->name,
            'currencyCode'    => $account->currency?->code,
            'currencySymbol'  => $account->currency?->symbol ?? '',
            'customerName'    => $customer
                ? (trim("{$customer->first_name} {$customer->last_name}") ?: ($customer->business_name ?? ''))
                : null,
            'cifNumber'       => $customer?->cif_number,
            'periodFrom'      => \Illuminate\Support\Carbon::parse($from)->format('d M Y'),
            'periodTo'        => \Illuminate\Support\Carbon::parse($to)->format('d M Y'),
            'openingBalance'  => number_format($openingBalance, 2),
            'closingBalance'  => number_format($runningBalance, 2),
            'totalDebit'      => number_format($totalDebit, 2),
            'totalCredit'     => number_format($totalCredit, 2),
            'transactions'    => $rows,
        ];
    }

    private function imageDataUri(?string $path): ?string
    {
        $contents = $this->files->read($path);

        if (! $contents) {
            return null;
        }

        $mime = str_ends_with((string) $path, '.png') ? 'image/png' : 'image/jpeg';

        return "data:{$mime};base64," . base64_encode($contents);
    }

    // Password-protected with the account number — a statement is financial information
    // about the customer, so opening it on any desktop requires proving you already know
    // that account's number, not just having the file. dompdf's default CPDF backend is
    // the only one of its three renderers that supports encryption (setEncryption() lives
    // on the underlying Cpdf object, reachable via the canvas only after render()).
    public function renderStatementPdf(Account $account, ?string $from, ?string $to): string
    {
        $data = $this->statementData($account, $from, $to);

        $pdf    = Pdf::loadView('accounts.statement', $data)->setPaper('a4', 'portrait');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();

        if (method_exists($canvas, 'get_cpdf')) {
            $canvas->get_cpdf()->setEncryption($account->account_number, '', [
                'print'  => true,
                'copy'   => false,
                'modify' => false,
            ]);
        }

        return $dompdf->output();
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

        $this->notifications->notifyPermission(
            'create_accounts',
            'account',
            $account->id,
            'New account opened',
            "Account '{$account->account_number}' was opened for customer '{$customer->cif_number}'.",
        );

        // primary_account is only true for the very first account a customer ever gets —
        // set once, at creation, by the line above — so it doubles as "is this the
        // welcome email or the additional-account email" without any extra flag.
        // CoreBankMail implements ShouldQueue, so send() queues it rather than blocking
        // this request on delivery.
        if ($customer->email) {
            Mail::to($customer->email)->send(
                $account->primary_account
                    ? $this->welcomeMail($customer, $account)
                    : $this->accountAddedMail($customer, $account)
            );
        }

        return $account->load(['accountProduct', 'currency', 'accountTier']);
    }

    private function customerDisplayName(Customer $customer): string
    {
        return trim("{$customer->first_name} {$customer->last_name}") ?: ($customer->business_name ?? '');
    }

    private function welcomeMail(Customer $customer, Account $account): CoreBankMail
    {
        return new CoreBankMail(
            subjectLine: 'Welcome to ' . config('app.name'),
            content: "Dear {$this->customerDisplayName($customer)},<br><br>"
                . "Welcome aboard! Your {$account->accountProduct?->name} account has been successfully opened.<br><br>"
                . "Bank Name: <strong>" . config('app.name') . "</strong><br>"
                . "Account Name: <strong>{$account->account_name}</strong><br>"
                . "Account Number: <strong>{$account->account_number}</strong><br><br>"
                . "Thank you for banking with us.",
        );
    }

    private function accountAddedMail(Customer $customer, Account $account): CoreBankMail
    {
        return new CoreBankMail(
            subjectLine: 'New Account Opened — ' . $account->accountProduct?->name,
            content: "Dear {$this->customerDisplayName($customer)},<br><br>"
                . "A new {$account->accountProduct?->name} account has been added to your profile.<br><br>"
                . "Bank Name: <strong>" . config('app.name') . "</strong><br>"
                . "Account Name: <strong>{$account->account_name}</strong><br>"
                . "Account Number: <strong>{$account->account_number}</strong><br><br>"
                . "Thank you for banking with us.",
        );
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

    // Liens now go through AccountLienService's maker-checker flow (place/approve/reject/
    // release) instead of being applied here directly.

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

        $this->notifications->notifyPermission(
            'create_accounts',
            'account',
            $account->id,
            'New account opened',
            "Account '{$account->account_number}' was opened for customer '{$customer->cif_number}'.",
        );

        return $account;
    }

    // Individuals carry their own BVN. Business-like customers have no BVN of their own —
    // either a director (if any were added — optional, not tied to a separate "corporate"
    // type) or a signatory with a BVN qualifies.
    private function isEligibleForAutoOpening(Customer $customer): bool
    {
        $hasBvn = match (true) {
            in_array($customer->customer_type, CustomerType::businessLike(), true) =>
                $customer->directors()->whereNotNull('bvn')->exists()
                || $customer->signatories()->whereNotNull('bvn')->exists(),
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

    // Keeps every account's account_name in step with the customer's own name — called
    // after a customer update that touches first_name/last_name/business_name, since
    // account_name is otherwise only ever set once, at account-opening time.
    public function syncAccountNamesForCustomer(Customer $customer): void
    {
        $customer->accounts()->update(['account_name' => $this->accountName($customer)]);
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
