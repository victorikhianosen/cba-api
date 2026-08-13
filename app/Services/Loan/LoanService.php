<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanTransaction;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Mirrors InvestmentService's open()/approve()/reject() shape closely. One structural
// difference: disbursement moves money the OPPOSITE direction of investment funding —
// a loan debits the LOAN_PORTFOLIO asset GL and credits the customer's account (money
// going OUT, the bank extending new credit), where investment funding debits the
// customer and credits an internal GL (money coming IN).
class LoanService
{
    // No fixed prefix — loan account_number lives in its own dedicated column, so it
    // can't collide with AccountService's per-product-type prefixes, same reasoning as
    // Investment's empty prefix.
    private const ACCOUNT_NUMBER_PREFIX = '';

    public function __construct(
        private AuditService $audit,
        private LoanScheduleService $schedules,
        private LoanTransactionService $transactions,
        private LoanCollateralService $collaterals,
        private LoanGuarantorService $guarantors,
        private NotificationService $notifications,
        private FileStorageService $files,
    ) {}

    // Restructure/Top-Up/Refinance/Write-Off are all maker-checker lifecycle requests —
    // "pending" on any of them means something on this loan is sitting in someone's
    // approval queue, which is otherwise invisible without opening the loan and clicking
    // into its Restructuring tab. $hasPendingRequests and $sortByPendingRequests let the
    // list surface and prioritize exactly that. Recovery is deliberately excluded — it's
    // a single-step record of funds already collected, not a maker-checker request.
    public function list(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?int $branchId = null,
        ?int $loanOfficerId = null,
        bool $hasPendingRequests = false,
        bool $sortByPendingRequests = false,
    ): LengthAwarePaginator {
        return Loan::query()
            ->with(['customer', 'loanProduct', 'disbursementAccount', 'branch', 'loanOfficer', 'createdBy', 'reviewedBy', 'approvedBy'])
            ->withCount([
                'restructures as pending_restructures_count' => fn ($query) => $query->where('status', 'pending'),
                'topups as pending_topups_count'               => fn ($query) => $query->where('status', 'pending'),
                'refinances as pending_refinances_count'       => fn ($query) => $query->where('status', 'pending'),
                'writeOffs as pending_write_offs_count'         => fn ($query) => $query->where('status', 'pending'),
                'repayments as pending_repayments_count'         => fn ($query) => $query->where('status', 'pending'),
            ])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($loanOfficerId, fn ($query, $loanOfficerId) => $query->where('loan_officer_id', $loanOfficerId))
            ->when($hasPendingRequests, function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('restructures', fn ($q) => $q->where('status', 'pending'))
                        ->orWhereHas('topups', fn ($q) => $q->where('status', 'pending'))
                        ->orWhereHas('refinances', fn ($q) => $q->where('status', 'pending'))
                        ->orWhereHas('writeOffs', fn ($q) => $q->where('status', 'pending'))
                        ->orWhereHas('repayments', fn ($q) => $q->where('status', 'pending'));
                });
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('account_number', 'like', "%{$search}%")
                        ->orWhereHas('disbursementAccount', function ($query) use ($search) {
                            $query->where('account_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query->where('cif_number', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('business_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(
                $sortByPendingRequests,
                fn ($query) => $query->orderByRaw(
                    '(pending_restructures_count + pending_topups_count + pending_refinances_count + pending_write_offs_count + pending_repayments_count) desc',
                ),
                fn ($query) => $query->latest(),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Loan
    {
        return Loan::with(['customer', 'loanProduct', 'disbursementAccount', 'currency', 'branch', 'loanOfficer', 'createdBy', 'reviewedBy', 'approvedBy'])
            ->withCount([
                'restructures as pending_restructures_count' => fn ($query) => $query->where('status', 'pending'),
                'topups as pending_topups_count'               => fn ($query) => $query->where('status', 'pending'),
                'refinances as pending_refinances_count'       => fn ($query) => $query->where('status', 'pending'),
                'writeOffs as pending_write_offs_count'         => fn ($query) => $query->where('status', 'pending'),
                'repayments as pending_repayments_count'         => fn ($query) => $query->where('status', 'pending'),
            ])
            ->findOrFail($id);
    }

    public function transactionsFor(int $loanId, int $perPage = 20)
    {
        $this->find($loanId);

        return LoanTransaction::where('loan_id', $loanId)
            ->with(['posting', 'createdBy'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function currentScheduleFor(int $loanId)
    {
        $loan = $this->find($loanId);
        $latestVersion = (int) ($loan->schedules()->max('version') ?: 1);

        return $loan->schedules()->where('version', $latestVersion)->orderBy('installment_number')->get();
    }

    public function previewSchedule(array $data): array
    {
        $product = LoanProduct::findOrFail($data['loan_product_id']);

        $this->assertBookingTermsAreValid($product, (string) $data['principal_amount']);

        return $this->schedules->preview(
            product: $product,
            principal: (string) $data['principal_amount'],
            duration: (int) $data['duration'],
        );
    }

    public function previewApprovalSchedule(int $loanId): array
    {
        $loan = $this->find($loanId);

        if ($loan->status !== 'reviewed') {
            throw ValidationException::withMessages([
                'status' => ['Only a reviewed loan has an approval schedule to preview.'],
            ]);
        }

        return $this->schedules->previewForLoan($loan);
    }

    public function open(array $data): Loan
    {
        $actor = Auth::guard('user')->user();

        $loan = $this->transactionWithAccountNumberRetry(function () use ($data) {
            $product = LoanProduct::findOrFail($data['loan_product_id']);

            if ($product->status !== 'active') {
                throw ValidationException::withMessages([
                    'loan_product_id' => ['Only active loan products can be booked against.'],
                ]);
            }

            $disbursementAccount = Account::with(['customer', 'accountProduct'])->findOrFail($data['disbursement_account_id']);

            if (! $disbursementAccount->customer) {
                throw ValidationException::withMessages([
                    'disbursement_account_id' => ['This account has no owning customer and cannot receive a loan disbursement.'],
                ]);
            }

            // Only Savings and Current accounts can receive a loan disbursement — a
            // domiciliary/wallet/virtual/investment account isn't a customer's everyday
            // spending account, so extending credit into one doesn't make sense.
            if (! in_array($disbursementAccount->accountProduct?->product_type, ['savings', 'current'], true)) {
                throw ValidationException::withMessages([
                    'disbursement_account_id' => ['A loan can only be disbursed into a Savings or Current account.'],
                ]);
            }

            if ((int) $disbursementAccount->currency_id !== (int) $product->currency_id) {
                throw ValidationException::withMessages([
                    'disbursement_account_id' => ['The disbursement account currency does not match the loan product currency.'],
                ]);
            }

            $principal          = (string) $data['principal_amount'];
            $duration = (int) $data['duration'];

            $this->assertBookingTermsAreValid($product, $principal);

            $loanFilePath = null;
            if (isset($data['loan_file']) && $data['loan_file'] instanceof UploadedFile) {
                $loanFilePath = $this->files->upload($data['loan_file'], 'loan-files');
            }

            $loan = Loan::create([
                'customer_id'                          => $disbursementAccount->customer->id,
                'branch_id'                              => $data['branch_id'] ?? $disbursementAccount->customer->branch_id,
                'loan_officer_id'                        => $data['loan_officer_id'] ?? $disbursementAccount->customer->account_officer_id,
                'loan_product_id'                      => $product->id,
                'disbursement_account_id'               => $disbursementAccount->id,
                'currency_id'                            => $product->currency_id,
                'account_number'                         => $this->generateUniqueAccountNumber(),
                'principal_amount'                       => $principal,
                'loan_file_path'                          => $loanFilePath,

                'interest_rate'                          => $product->interest_rate,
                'interest_rate_frequency_type'            => $product->interest_rate_frequency_type,
                'interest_method'                        => $product->interest_method,
                'repayment_every'                        => $product->repayment_every,
                'repayment_frequency_type'                => $product->repayment_frequency_type,
                'duration'                    => $duration,
                'arrears_tolerance_amount'                => $product->arrears_tolerance_amount,
                'penalty_interest_rate'                    => $product->penalty_interest_rate,
                'payment_allocation_order'                => $product->payment_allocation_order ?? Loan::DEFAULT_PAYMENT_ALLOCATION_ORDER,

                'status'      => 'pending',
                'created_by'  => Auth::guard('user')->id(),
            ])->refresh();

            // Optional, booking-time collateral/guarantors — same create() paths used by
            // the standalone LoanCollateralController/LoanGuarantorController, just called
            // inline so they land in the same transaction as the loan itself (all-or-none).
            foreach ($data['collaterals'] ?? [] as $collateral) {
                $this->collaterals->create($loan, $collateral);
            }

            foreach ($data['guarantors'] ?? [] as $guarantor) {
                $this->guarantors->create($loan, $guarantor);
            }

            return $loan;
        });

        $this->audit->log(
            action: 'created',
            module: 'loans',
            auditable: $loan,
            after: $loan->toArray(),
            description: "Loan '{$loan->account_number}' was booked for customer '{$loan->customer?->cif_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_loans',
            'loan',
            $loan->id,
            'New loan request',
            "Loan '{$loan->account_number}' is awaiting your approval.",
        );

        return $loan->load(['customer', 'loanProduct', 'disbursementAccount', 'collaterals', 'guarantors']);
    }

    // First stage of the maker->reviewer->approver chain: a second, distinct user
    // reviews a pending loan before it can be approved. Mirrors
    // FundGlService::review() exactly.
    public function review(Loan $loan): Loan
    {
        $actor = Auth::guard('user')->user();

        if ($actor && (int) $loan->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot review a loan you booked yourself.'],
            ]);
        }

        DB::transaction(function () use ($loan, $actor) {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Only pending loans can be reviewed.'],
                ]);
            }

            $loan->update([
                'status'      => 'reviewed',
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
            ]);
        });

        $loan->refresh();

        $this->audit->log(
            action: 'reviewed',
            module: 'loans',
            auditable: $loan,
            after: ['status' => 'reviewed'],
            description: "Loan '{$loan->account_number}' was reviewed by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_loans',
            'loan',
            $loan->id,
            'Loan awaiting approval',
            "Loan '{$loan->account_number}' has been reviewed and is awaiting your approval.",
        );

        return $loan;
    }

    // Approving the loan IS the authorization to disburse — one admin books it, a
    // second admin reviews it, a third admin approves+TOTPs it and the principal is
    // released immediately — three distinct users, mirroring FundGlService's
    // maker->reviewer->approver chain. $skipSelfApprovalCheck is only ever true when a
    // caller other than the ordinary booking flow already went through its own
    // maker-checker gate before reaching here (e.g. LoanRefinanceService — the
    // refinance request itself was already maker-checked, and the new loan it
    // books+approves in the same step isn't an independent decision the same admin is
    // short-circuiting, so it also has no separate human reviewer and skips straight
    // from 'pending').
    public function approve(Loan $loan, bool $skipSelfApprovalCheck = false): Loan
    {
        $requiredStatus = $skipSelfApprovalCheck ? 'pending' : 'reviewed';

        if ($loan->status !== $requiredStatus) {
            throw ValidationException::withMessages([
                'status' => [$skipSelfApprovalCheck
                    ? 'Only pending loans can be approved.'
                    : 'Only reviewed loans can be approved — a second admin must review this loan first.'],
            ]);
        }

        $before = $loan->status;
        $actor  = Auth::guard('user')->user();

        if (! $skipSelfApprovalCheck && $actor && (int) $loan->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a loan you booked yourself.'],
            ]);
        }

        if (! $skipSelfApprovalCheck && $actor && (int) $loan->reviewed_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a loan you reviewed yourself — the maker, reviewer, and approver must be three different users.'],
            ]);
        }

        $loan = DB::transaction(function () use ($loan, $requiredStatus) {
            $lockedLoan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($lockedLoan->status !== $requiredStatus) {
                throw ValidationException::withMessages([
                    'status' => [$requiredStatus === 'pending'
                        ? 'Only pending loans can be approved.'
                        : 'Only reviewed loans can be approved — a second admin must review this loan first.'],
                ]);
            }

            $disbursementAccount = Account::findOrFail($loan->disbursement_account_id);

            // Disbursement: debit LOAN_PORTFOLIO (asset — the bank now holds a loan
            // receivable), credit the customer's disbursement account (they now have the
            // money) — a real, balanced two-legged entry, not a FUND_SOURCE placeholder.
            $financialTransaction = $this->transactions->moveLoanPortfolio(
                loan: $loan,
                account: $disbursementAccount,
                amount: (string) $loan->principal_amount,
                accountIsCredited: true,
                transactionType: 'loan_disbursement',
                description: "Disbursement for loan '{$loan->account_number}'.",
            );

            $startDate    = now()->toDateString();
            $maturityDate = $this->computeMaturityDate($loan);

            $loan->update([
                'status'                  => 'active',
                'start_date'              => $startDate,
                'maturity_date'           => $maturityDate,
                'next_due_date'           => null, // set below once the schedule generates
                'approved_by'             => Auth::guard('user')->id(),
                'approved_at'             => now(),
            ]);

            $loan = $loan->refresh();

            $this->schedules->generate($loan);

            $this->transactions->record(
                loan: $loan,
                type: 'disbursement',
                amount: (string) $loan->principal_amount,
                description: "Initial disbursement '{$financialTransaction->reference}'.",
                transactionReference: $financialTransaction->transaction_reference,
            );

            $firstDueDate = $loan->schedules()->orderBy('installment_number')->value('due_date');
            $loan->update(['next_due_date' => $firstDueDate]);

            return $loan->refresh();
        });

        $this->audit->log(
            action: 'approved',
            module: 'loans',
            auditable: $loan,
            before: ['status' => $before],
            after: ['status' => $loan->status, 'disbursement_posting_id' => $loan->disbursement_posting_id],
            description: "Loan '{$loan->account_number}' was approved by '{$actor?->username}' and disbursed immediately.",
        );

        $this->notifications->notifyUser(
            $loan->createdBy,
            'loan',
            $loan->id,
            'Loan approved',
            "Your loan '{$loan->account_number}' was approved and disbursed.",
        );

        $customer = $loan->customer;
        $this->notifications->notifyCustomerEmail(
            actor: $actor,
            customer: $customer,
            type: 'loan_disbursement',
            subject: 'Your Loan Has Been Disbursed',
            body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                . "Your loan on account <strong>{$loan->account_number}</strong> has been approved and disbursed.<br><br>"
                . "Principal Disbursed: <strong>" . number_format((float) $loan->principal_amount, 2) . "</strong><br>"
                . "Disbursed To: <strong>{$loan->disbursementAccount?->account_number}</strong><br>"
                . "Maturity Date: <strong>{$loan->maturity_date}</strong><br><br>"
                . "Thank you for banking with us.",
        );

        return $loan;
    }

    public function reject(Loan $loan, string $reason): Loan
    {
        if (! in_array($loan->status, ['pending', 'reviewed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only pending or reviewed loans can be rejected.'],
            ]);
        }

        $before = $loan->status;
        $actor  = Auth::guard('user')->user();

        $loan->update(['status' => 'rejected']);
        $loan->refresh();

        $this->audit->log(
            action: 'rejected',
            module: 'loans',
            auditable: $loan,
            before: ['status' => $before],
            after: ['status' => $loan->status, 'reason' => $reason],
            description: "Loan '{$loan->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyUser(
            $loan->createdBy,
            'loan',
            $loan->id,
            'Loan rejected',
            "Your loan '{$loan->account_number}' was rejected: {$reason}",
        );

        $customer = $loan->customer;
        $this->notifications->notifyCustomerEmail(
            actor: $actor,
            customer: $customer,
            type: 'loan_rejected',
            subject: 'Your Loan Application Was Not Approved',
            body: "Dear " . trim("{$customer?->first_name} {$customer?->last_name}") . ",<br><br>"
                . "We're sorry to inform you that your loan application on account <strong>{$loan->account_number}</strong> was not approved.<br><br>"
                . "Reason: <strong>{$reason}</strong><br><br>"
                . "Please reach out to us if you have any questions.",
        );

        return $loan;
    }

    private function computeMaturityDate(Loan $loan): string
    {
        $totalUnits = $loan->repayment_every * $loan->duration;

        return match ($loan->repayment_frequency_type) {
            'days'   => now()->addDays($totalUnits)->toDateString(),
            'weeks'  => now()->addWeeks($totalUnits)->toDateString(),
            'months' => now()->addMonths($totalUnits)->toDateString(),
            default  => now()->addMonths($totalUnits)->toDateString(),
        };
    }

    private function assertBookingTermsAreValid(LoanProduct $product, string $principal): void
    {
        if ($product->min_principal_amount !== null && bccomp($principal, (string) $product->min_principal_amount, 2) < 0) {
            throw ValidationException::withMessages([
                'principal_amount' => ["Principal must be at least {$product->min_principal_amount}."],
            ]);
        }

        if ($product->max_principal_amount !== null && bccomp($principal, (string) $product->max_principal_amount, 2) > 0) {
            throw ValidationException::withMessages([
                'principal_amount' => ["Principal cannot exceed {$product->max_principal_amount}."],
            ]);
        }
    }

    private function transactionWithAccountNumberRetry(callable $callback): Loan
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction($callback);
            } catch (\Illuminate\Database\QueryException $e) {
                $attempts++;

                if ($attempts >= 3 || $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
    }

    private function generateUniqueAccountNumber(): string
    {
        $lastNumber = Loan::where('account_number', 'like', self::ACCOUNT_NUMBER_PREFIX . '%')
            ->lockForUpdate()
            ->orderByDesc('account_number')
            ->value('account_number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen(self::ACCOUNT_NUMBER_PREFIX))) + 1 : 1;

        return self::ACCOUNT_NUMBER_PREFIX . str_pad((string) $nextSequence, 10, '0', STR_PAD_LEFT);
    }
}
