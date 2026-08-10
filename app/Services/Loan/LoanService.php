<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Enums\Product\LoanProductType;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanTransaction;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Posting\PostingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Mirrors InvestmentService's open()/approve()/reject() shape closely. One structural
// difference: disbursement moves money the OPPOSITE direction of investment funding —
// a loan debits the bank's FUND_SOURCE GL and credits the customer's account (money
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
        private PostingService $postings,
        private LoanScheduleService $schedules,
        private LoanTransactionService $transactions,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15, ?string $search = null, ?string $status = null, ?int $branchId = null, ?int $loanOfficerId = null): LengthAwarePaginator
    {
        return Loan::query()
            ->with(['customer', 'loanProduct', 'disbursementAccount', 'branch', 'loanOfficer', 'createdBy', 'approvedBy'])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($loanOfficerId, fn ($query, $loanOfficerId) => $query->where('loan_officer_id', $loanOfficerId))
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
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Loan
    {
        return Loan::with(['customer', 'loanProduct', 'disbursementAccount', 'currency', 'branch', 'loanOfficer', 'createdBy', 'approvedBy'])->findOrFail($id);
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

        $this->assertBookingTermsAreValid($product, (string) $data['principal_amount'], (int) $data['number_of_repayments']);

        return $this->schedules->preview(
            product: $product,
            principal: (string) $data['principal_amount'],
            numberOfRepayments: (int) $data['number_of_repayments'],
        );
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

            $disbursementAccount = Account::with('customer')->findOrFail($data['disbursement_account_id']);

            if (! $disbursementAccount->customer) {
                throw ValidationException::withMessages([
                    'disbursement_account_id' => ['This account has no owning customer and cannot receive a loan disbursement.'],
                ]);
            }

            if ((int) $disbursementAccount->currency_id !== (int) $product->currency_id) {
                throw ValidationException::withMessages([
                    'disbursement_account_id' => ['The disbursement account currency does not match the loan product currency.'],
                ]);
            }

            $principal          = (string) $data['principal_amount'];
            $numberOfRepayments = (int) $data['number_of_repayments'];

            $this->assertBookingTermsAreValid($product, $principal, $numberOfRepayments);

            return Loan::create([
                'customer_id'                          => $disbursementAccount->customer->id,
                'branch_id'                              => $data['branch_id'] ?? $disbursementAccount->customer->branch_id,
                'loan_officer_id'                        => $data['loan_officer_id'] ?? $disbursementAccount->customer->account_officer_id,
                'loan_product_id'                      => $product->id,
                'disbursement_account_id'               => $disbursementAccount->id,
                'currency_id'                            => $product->currency_id,
                'account_number'                         => $this->generateUniqueAccountNumber(),
                'principal_amount'                       => $principal,

                'interest_rate'                          => $product->interest_rate,
                'interest_rate_frequency_type'            => $product->interest_rate_frequency_type,
                'interest_method'                        => $product->interest_method,
                'interest_calculation_period_type'        => $product->interest_calculation_period_type,
                'interest_calculation_days_in_year'      => $product->interest_calculation_days_in_year,
                'repayment_every'                        => $product->repayment_every,
                'repayment_frequency_type'                => $product->repayment_frequency_type,
                'number_of_repayments'                    => $numberOfRepayments,
                'amortization_method'                    => $product->amortization_method,
                'grace_on_principal_periods'              => $product->grace_on_principal_periods,
                'grace_on_interest_periods'                => $product->grace_on_interest_periods,
                'grace_interest_free_periods'              => $product->grace_interest_free_periods,
                'grace_on_arrears_ageing'                  => $product->grace_on_arrears_ageing,
                'arrears_tolerance_amount'                => $product->arrears_tolerance_amount,
                'penalty_interest_rate'                    => $product->penalty_interest_rate,
                'payment_allocation_order'                => $product->payment_allocation_order ?? Loan::DEFAULT_PAYMENT_ALLOCATION_ORDER,
                'days_in_month_type'                      => $product->days_in_month_type,

                'status'      => 'pending',
                'created_by'  => Auth::guard('user')->id(),
            ])->refresh();
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

        return $loan->load(['customer', 'loanProduct', 'disbursementAccount']);
    }

    // Approving the loan IS the authorization to disburse — one admin books it, a
    // different admin approves+TOTPs it and the principal is released immediately, same
    // "opening and approving already requires two people" precedent as Investment.
    public function approve(Loan $loan): Loan
    {
        if ($loan->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending loans can be approved.'],
            ]);
        }

        $before = $loan->status;
        $actor  = Auth::guard('user')->user();

        if ($actor && (int) $loan->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a loan you booked yourself.'],
            ]);
        }

        $loan = DB::transaction(function () use ($loan) {
            $product = LoanProduct::with('generalLedgerMappings')->findOrFail($loan->loan_product_id);

            $fundSourceMapping = $product->generalLedgerMappings
                ->firstWhere('financial_account_type', LoanProductType::FUND_SOURCE->value);

            if (! $fundSourceMapping) {
                throw ValidationException::withMessages([
                    'loan_product_id' => ['This loan product has no Fund Source GL mapping configured.'],
                ]);
            }

            // Disbursement: debit FUND_SOURCE (internal, releases the funds), credit the
            // customer's disbursement account (they now have the money) — reverse
            // direction from investment funding.
            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $fundSourceMapping->general_ledger_id,
                'destination_type' => 'account',
                'destination_id'   => $loan->disbursement_account_id,
                'amount'           => (string) $loan->principal_amount,
                'module'           => 'loans',
                'transaction_type' => 'loan_disbursement',
                'remarks'          => "Disbursement for loan '{$loan->account_number}'.",
            ]);

            $startDate    = now()->toDateString();
            $maturityDate = $this->computeMaturityDate($loan);

            $loan->update([
                'disbursement_posting_id' => $financialTransaction->posting_id,
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
                posting: $financialTransaction->posting,
                description: "Initial disbursement via posting '{$financialTransaction->posting?->reference}'.",
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

        return $loan;
    }

    public function reject(Loan $loan, string $reason): Loan
    {
        if ($loan->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending loans can be rejected.'],
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

        return $loan;
    }

    private function computeMaturityDate(Loan $loan): string
    {
        $totalUnits = $loan->repayment_every * $loan->number_of_repayments;

        return match ($loan->repayment_frequency_type) {
            'days'   => now()->addDays($totalUnits)->toDateString(),
            'weeks'  => now()->addWeeks($totalUnits)->toDateString(),
            'months' => now()->addMonths($totalUnits)->toDateString(),
            default  => now()->addMonths($totalUnits)->toDateString(),
        };
    }

    private function assertBookingTermsAreValid(LoanProduct $product, string $principal, int $numberOfRepayments): void
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

        if ($product->min_number_of_repayments !== null && $numberOfRepayments < $product->min_number_of_repayments) {
            throw ValidationException::withMessages([
                'number_of_repayments' => ["Number of repayments must be at least {$product->min_number_of_repayments}."],
            ]);
        }

        if ($product->max_number_of_repayments !== null && $numberOfRepayments > $product->max_number_of_repayments) {
            throw ValidationException::withMessages([
                'number_of_repayments' => ["Number of repayments cannot exceed {$product->max_number_of_repayments}."],
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
