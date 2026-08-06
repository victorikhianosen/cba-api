<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Enums\Product\InvestmentProductType;
use App\Models\Account;
use App\Models\Investment;
use App\Models\InvestmentProduct;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use App\Services\Posting\PostingService;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvestmentService
{
    // Fixed prefix, distinct from AccountService::ACCOUNT_NUMBER_PREFIX_BY_PRODUCT_TYPE
    // (1=savings, 2=current, 3=domiciliary, 9=fallback) — investments are never opened
    // through that map, so a dedicated digit avoids any future collision.
    private const ACCOUNT_NUMBER_PREFIX = '4';

    public function __construct(
        private AuditService $audit,
        private PostingService $postings,
        private InvestmentScheduleService $schedules,
        private InvestmentTransactionService $transactions,
        private NotificationService $notifications,
        private InvestmentLetterTemplateService $letterTemplates,
        private FileStorageService $files,
    ) {}

    public function list(int $perPage = 15, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return Investment::query()
            ->with(['customer', 'investmentProduct', 'sourceAccount'])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('account_number', 'like', "%{$search}%")
                        ->orWhereHas('sourceAccount', function ($query) use ($search) {
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

    public function find(int $id): Investment
    {
        return Investment::with(['customer', 'investmentProduct', 'sourceAccount', 'schedules', 'transactions', 'pendingTopupRequestedBy'])->findOrFail($id);
    }

    // Shared by the print/download endpoint and the email endpoint so both always
    // render the exact same letter content.
    public function letterData(Investment $investment): array
    {
        $investment->loadMissing(['customer', 'investmentProduct', 'sourceAccount', 'currency']);

        $customer = $investment->customer;
        $template = $this->letterTemplates->getDefault();

        // Real expected interest from the current schedule version (reflects any top-up
        // already applied), not a fresh recompute — same source the "Current Schedule" UI
        // tab reads from.
        $latestVersion   = (int) ($investment->schedules()->max('version') ?: 1);
        $grossInterest   = (float) $investment->schedules()->where('version', $latestVersion)->sum('expected_interest_amount');
        $withholdingRate = (float) ($investment->withholding_tax_rate ?? 0);
        $hasWithholdingTax = $withholdingRate > 0;
        $withholdingTaxAmount = $hasWithholdingTax ? $grossInterest * ($withholdingRate / 100) : 0.0;
        $netInterest          = $grossInterest - $withholdingTaxAmount;

        return [
            'companyName'           => config('app.name'),
            'companyAddress'        => config('app.address', ''),
            'issuedDate'            => now()->format('F j, Y'),
            'generatedAt'           => now()->format('F j, Y g:i A'),
            'reference'             => 'INV-LTR-' . $investment->account_number,
            'customerName'          => trim("{$customer?->first_name} {$customer?->last_name}") ?: ($customer?->business_name ?? ''),
            'customerAddress'       => null,
            'cifNumber'             => $customer?->cif_number,
            'accountNumber'         => $investment->account_number,
            'productName'           => $investment->investmentProduct?->name,
            'currencySymbol'        => $investment->currency?->symbol ?? '',
            'principalAmount'       => number_format((float) $investment->principal_amount, 2),
            'currentPrincipal'      => number_format((float) $investment->current_principal, 2),
            'interestRate'          => number_format((float) $investment->interest_rate, 2),
            'interestPostingPeriod' => ucfirst((string) $investment->interest_posting_period),
            'tenure'                => $investment->tenure,
            'startDate'             => $investment->start_date?->format('F j, Y'),
            'maturityDate'          => $investment->maturity_date?->format('F j, Y'),
            'maturityValue'         => number_format((float) $investment->maturity_value, 2),
            'autoRenew'             => $investment->auto_renew ? 'Yes' : 'No',
            'sourceAccountNumber'   => $investment->sourceAccount?->account_number,
            'status'                => ucfirst((string) $investment->status),

            'grossInterestAmount'   => number_format($grossInterest, 2),
            'hasWithholdingTax'     => $hasWithholdingTax,
            'withholdingTaxRate'    => number_format($withholdingRate, 2),
            'withholdingTaxAmount'  => number_format($withholdingTaxAmount, 2),
            'netInterestAmount'     => number_format($netInterest, 2),

            // Base64 data URIs, not URLs — dompdf can't reliably fetch remote/http images
            // without extra config, so the active letter template's images (if any) are
            // embedded directly. Everything is null when no default template exists yet,
            // and the Blade view falls back to its generic text/line in that case.
            'headerImageBase64'          => $this->imageDataUri($template?->header_image),
            'signatoryOneName'           => $template?->signatory_one_name,
            'signatoryOneSignatureBase64' => $this->imageDataUri($template?->signatory_one_signature),
            'signatoryTwoName'           => $template?->signatory_two_name,
            'signatoryTwoSignatureBase64' => $this->imageDataUri($template?->signatory_two_signature),
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

    public function transactionsFor(int $investmentId, int $perPage = 20): LengthAwarePaginator
    {
        $investment = Investment::findOrFail($investmentId);

        return $investment->transactions()
            ->with(['createdBy', 'posting'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    // Only the live version — small and bounded (one row per interest installment for the
    // current term), so no pagination needed. The "Previous Schedules" history is a
    // deliberately separate, lazy-loaded call (previousScheduleFor()) so a heavily
    // topped-up investment doesn't download every historical version on every page load.
    public function currentScheduleFor(int $investmentId): \Illuminate\Support\Collection
    {
        $investment    = Investment::findOrFail($investmentId);
        $latestVersion = (int) ($investment->schedules()->max('version') ?: 1);

        return $investment->schedules()
            ->where('version', $latestVersion)
            ->orderBy('installment_number')
            ->get();
    }

    // Every version older than the current one, newest-first — fetched only when the UI's
    // "Previous Schedules" section is actually expanded.
    public function previousScheduleFor(int $investmentId, int $perPage = 100): LengthAwarePaginator
    {
        $investment    = Investment::findOrFail($investmentId);
        $latestVersion = (int) ($investment->schedules()->max('version') ?: 1);

        return $investment->schedules()
            ->where('version', '<', $latestVersion)
            ->orderByDesc('version')
            ->orderBy('installment_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    // Computes the same forward-looking schedule generate() would eventually persist, for a
    // product/principal/tenure/posting-period combination that hasn't been booked yet — so
    // the UI can show the projected installments before anyone commits to opening it.
    // Read-only: nothing is written to the database.
    public function previewSchedule(array $data): array
    {
        $product = InvestmentProduct::findOrFail($data['investment_product_id']);
        $tenure  = (int) $data['tenure'];

        $this->assertBookingTermsAreValid($product, (string) $data['principal_amount'], $data['interest_posting_period'], $tenure);

        return $this->schedules->preview($product, (float) $data['principal_amount'], $data['interest_posting_period'], $tenure);
    }

    // Same preview computation as previewSchedule(), but sourced from an already-booked
    // pending investment's own locked-in terms instead of raw request data — so an approver
    // can see the schedule that will actually be generated before confirming approval, not
    // just before the customer booked it.
    public function previewApproval(Investment $investment): array
    {
        if ($investment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending investments have an approval preview.'],
            ]);
        }

        $product = InvestmentProduct::findOrFail($investment->investment_product_id);

        return $this->schedules->preview(
            $product,
            (float) $investment->principal_amount,
            $investment->interest_posting_period,
            (int) $investment->tenure,
        );
    }

    // No posting is created here — the debit only happens once the investment itself is
    // approved. This just stages the request as 'pending'. The customer is resolved from
    // the funding account itself (account-number-first booking) rather than a separate
    // customer selection step — one lookup tells you who owns it, what it's called, and
    // what currency/balance it holds.
    public function open(array $data): Investment
    {
        $actor = Auth::guard('user')->user();

        $investment = $this->transactionWithAccountNumberRetry(function () use ($data) {
            $product = InvestmentProduct::findOrFail($data['investment_product_id']);

            if ($product->status !== 'active') {
                throw ValidationException::withMessages([
                    'investment_product_id' => ['Only active investment products can be opened against.'],
                ]);
            }

            $sourceAccount = Account::with('customer')->findOrFail($data['source_account_id']);

            if (! $sourceAccount->customer) {
                throw ValidationException::withMessages([
                    'source_account_id' => ['This account has no owning customer and cannot fund an investment.'],
                ]);
            }

            if ((int) $sourceAccount->currency_id !== (int) $product->currency_id) {
                throw ValidationException::withMessages([
                    'source_account_id' => ['The source account currency does not match the investment product currency.'],
                ]);
            }

            $principal = (string) $data['principal_amount'];
            $tenure    = (int) $data['tenure'];

            $this->assertBookingTermsAreValid($product, $principal, $data['interest_posting_period'], $tenure);

            // Auto-renewal is opt-in per investment, not automatic just because the product
            // offers it — the customer may still choose not to roll over. rollover_type is
            // only required when they've actually opted in (a rollover_type was sent);
            // sending none is a valid choice, not a validation error.
            $rolloverType = $data['rollover_type'] ?? null;

            if ($rolloverType !== null) {
                if (! $product->auto_renew) {
                    throw ValidationException::withMessages([
                        'rollover_type' => ['This product does not offer auto-renewal.'],
                    ]);
                }

                if (! in_array($rolloverType, ['simple', 'compound'], true)) {
                    throw ValidationException::withMessages([
                        'rollover_type' => ['Rollover type must be simple or compound.'],
                    ]);
                }
            }

            return Investment::create([
                'customer_id'                    => $sourceAccount->customer->id,
                'investment_product_id'          => $product->id,
                'source_account_id'              => $sourceAccount->id,
                'currency_id'                     => $product->currency_id,
                'account_number'                  => $this->generateUniqueAccountNumber(),
                'principal_amount'                => $principal,
                'interest_rate'                   => $product->interest_rate,
                'interest_posting_period'         => $data['interest_posting_period'],
                'tenure'                           => $tenure,
                'early_liquidation_lock_days'      => $product->early_liquidation_lock_days,
                'early_liquidation_penalty_rate'   => $product->early_liquidation_penalty_rate,
                'withholding_tax_rate'             => $product->withholding_tax_rate,
                'minimum_retention_percentage'     => $product->minimum_retention_percentage,
                'auto_renew'                       => $rolloverType !== null,
                'rollover_type'                    => $rolloverType,
                'status'                           => 'pending',
                'created_by'                       => Auth::guard('user')->id(),
            ])->refresh();
        });

        $this->audit->log(
            action: 'created',
            module: 'investments',
            auditable: $investment,
            after: $investment->toArray(),
            description: "Investment '{$investment->account_number}' was opened for customer '{$investment->customer?->cif_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_investments',
            'investment',
            $investment->id,
            'New investment request',
            "Investment '{$investment->account_number}' is awaiting your approval.",
        );

        return $investment->load(['customer', 'investmentProduct', 'sourceAccount']);
    }

    // Approving the investment IS the authorization to move the customer's money into
    // it — there is no separate "someone else must also approve the posting" step.
    // A single admin action here debits the source account, credits the product's
    // Investment Funding GL, activates the investment, generates its schedule, and
    // records the funding transaction, all atomically. (The two-step "create a pending
    // posting, wait for a different admin to approve it via the Postings screen" design
    // was dropped — it doubled the clicks for money that was already authorized the
    // moment this approval happened, and maker-checker for investments is satisfied by
    // opening and approving already requiring two different people, same as every other
    // product in this system.)
    public function approve(Investment $investment): Investment
    {
        if ($investment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending investments can be approved.'],
            ]);
        }

        $before = $investment->status;
        $actor  = Auth::guard('user')->user();

        if ($actor && (int) $investment->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve an investment you booked yourself.'],
            ]);
        }

        $investment = DB::transaction(function () use ($investment) {
            $product = InvestmentProduct::with('generalLedgerMappings')->findOrFail($investment->investment_product_id);

            $fundingMapping = $product->generalLedgerMappings
                ->firstWhere('financial_account_type_name', InvestmentProductType::INVESTMENT_FUNDING->name);

            if (! $fundingMapping) {
                throw ValidationException::withMessages([
                    'investment_product_id' => ['This investment product has no Investment Funding GL mapping configured.'],
                ]);
            }

            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'account',
                'source_id'        => $investment->source_account_id,
                'destination_type' => 'general_ledger',
                'destination_id'   => $fundingMapping->general_ledger_id,
                'amount'           => (string) $investment->principal_amount,
                'module'           => 'investments',
                'transaction_type' => 'investment_funding',
                'remarks'          => "Funding for investment '{$investment->account_number}'.",
            ]);

            $startDate    = now()->toDateString();
            $maturityDate = now()->addDays($investment->tenure)->toDateString();

            $investment->update([
                'funding_posting_id' => $financialTransaction->posting_id,
                'status'             => 'active',
                'start_date'         => $startDate,
                'maturity_date'      => $maturityDate,
                'approved_by'        => Auth::guard('user')->id(),
                'approved_at'        => now(),
            ]);

            $investment = $investment->refresh();

            $this->schedules->generate($investment);

            $this->transactions->record(
                investment: $investment,
                type: 'funding',
                amount: (string) $investment->principal_amount,
                posting: $financialTransaction->posting,
                description: "Initial funding via posting '{$financialTransaction->posting?->reference}'.",
            );

            return $investment->refresh();
        });

        $this->audit->log(
            action: 'approved',
            module: 'investments',
            auditable: $investment,
            before: ['status' => $before],
            after: ['status' => $investment->status, 'funding_posting_id' => $investment->funding_posting_id],
            description: "Investment '{$investment->account_number}' was approved by '{$actor?->username}' and funded immediately.",
        );

        $this->notifications->notifyUser(
            $investment->createdBy,
            'investment',
            $investment->id,
            'Investment approved',
            "Your investment '{$investment->account_number}' was approved and funded.",
        );

        return $investment;
    }

    public function reject(Investment $investment, string $reason): Investment
    {
        if ($investment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending investments can be rejected.'],
            ]);
        }

        $before = $investment->status;
        $actor  = Auth::guard('user')->user();

        $investment->update(['status' => 'rejected']);
        $investment->refresh();

        $this->audit->log(
            action: 'rejected',
            module: 'investments',
            auditable: $investment,
            before: ['status' => $before],
            after: ['status' => $investment->status, 'reason' => $reason],
            description: "Investment '{$investment->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyUser(
            $investment->createdBy,
            'investment',
            $investment->id,
            'Investment rejected',
            "Your investment '{$investment->account_number}' was rejected: {$reason}",
        );

        return $investment;
    }

    // Read-only — shows what the schedule would look like both before and after a
    // prospective top-up, so the UI can render them side by side before anyone commits to
    // requesting it. Mirrors previewSchedule()'s "compute the same thing generate()/
    // regenerateRemaining() would persist, but don't write anything" pattern.
    public function previewTopUp(Investment $investment, string $amount): array
    {
        $product = $this->assertTopUpAllowed($investment, $amount);

        return $this->computeTopUpPreview($investment, $amount, $product);
    }

    // Same preview, but for a top-up that's already staged (pending_topup_amount is already
    // set) — used by the approve flow so a checker can see exactly what they're about to
    // apply before confirming, without re-validating "is a top-up already pending" (it is,
    // that's the point) or re-entering the amount.
    public function previewPendingTopUp(Investment $investment): array
    {
        if ($investment->pending_topup_amount === null) {
            throw ValidationException::withMessages([
                'status' => ['This investment has no top-up awaiting approval.'],
            ]);
        }

        $product = InvestmentProduct::findOrFail($investment->investment_product_id);

        return $this->computeTopUpPreview($investment, (string) $investment->pending_topup_amount, $product);
    }

    private function computeTopUpPreview(Investment $investment, string $amount, InvestmentProduct $product): array
    {
        $latestVersion = (int) ($investment->schedules()->max('version') ?: 1);

        $currentSchedule = $investment->schedules()
            ->where('version', $latestVersion)
            ->orderBy('installment_number')
            ->get();

        $currentTotalInterest = $currentSchedule->reduce(
            fn (string $carry, $row) => bcadd($carry, (string) $row->expected_interest_amount, 6),
            '0',
        );

        $newPrincipal  = bcadd((string) $investment->current_principal, $amount, 6);
        $today         = now()->startOfDay();
        $maturityDate  = \Carbon\Carbon::parse($investment->maturity_date);
        $remainingDays = max(1, $today->diffInDays($maturityDate));

        $projected = $this->schedules->preview(
            $product,
            (float) $newPrincipal,
            $investment->interest_posting_period,
            (int) $remainingDays,
            $today,
        );

        return [
            'current_principal'         => (string) $investment->current_principal,
            'new_principal'             => $newPrincipal,
            'current_schedule'          => $currentSchedule,
            'current_total_interest'    => $currentTotalInterest,
            'projected_schedule'        => $projected['installments'],
            'projected_total_interest'  => $projected['total_interest'],
        ];
    }

    // Stages a top-up request — no money moves and the investment's own status stays
    // 'active' the whole time (it's still a live, funded investment). The pending_topup_*
    // columns are the "is a top-up awaiting approval" state instead, so the UI can show
    // "Top-up Pending" without the investment disappearing from active-investment views.
    public function requestTopUp(Investment $investment, string $amount): Investment
    {
        $this->assertTopUpAllowed($investment, $amount);

        $actor = Auth::guard('user')->user();

        $investment->update([
            'pending_topup_amount'       => $amount,
            'pending_topup_requested_by' => $actor?->id,
            'pending_topup_requested_at' => now(),
        ]);

        $investment->refresh();

        $this->audit->log(
            action: 'topup_requested',
            module: 'investments',
            auditable: $investment,
            after: ['pending_topup_amount' => $amount],
            description: "A top-up of {$amount} was requested for investment '{$investment->account_number}' by '{$actor?->username}'.",
        );

        $this->notifications->notifyPermission(
            'approve_investments',
            'investment',
            $investment->id,
            'New top-up request',
            "A top-up of {$amount} for investment '{$investment->account_number}' is awaiting your approval.",
        );

        return $investment;
    }

    // Same single-step model as approve(): approving the top-up IS the authorization to
    // move the money, straight to the Investment Funding GL — no separate Postings-screen
    // detour. Also regenerates the remaining schedule on the new, larger principal.
    public function approveTopUp(Investment $investment): Investment
    {
        if ($investment->pending_topup_amount === null) {
            throw ValidationException::withMessages([
                'status' => ['This investment has no top-up awaiting approval.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        if ($actor && (int) $investment->pending_topup_requested_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'status' => ['You cannot approve a top-up you requested yourself.'],
            ]);
        }

        $topUpAmount    = (string) $investment->pending_topup_amount;
        $principalBefore = (string) $investment->current_principal;
        $topUpRequester = $investment->pendingTopupRequestedBy;

        $investment = DB::transaction(function () use ($investment, $topUpAmount, $principalBefore, $actor) {
            $product = InvestmentProduct::with('generalLedgerMappings')->findOrFail($investment->investment_product_id);

            $fundingMapping = $product->generalLedgerMappings
                ->firstWhere('financial_account_type_name', InvestmentProductType::INVESTMENT_FUNDING->name);

            if (! $fundingMapping) {
                throw ValidationException::withMessages([
                    'investment_product_id' => ['This investment product has no Investment Funding GL mapping configured.'],
                ]);
            }

            $financialTransaction = $this->postings->createAndApprove([
                'source_type'      => 'account',
                'source_id'        => $investment->source_account_id,
                'destination_type' => 'general_ledger',
                'destination_id'   => $fundingMapping->general_ledger_id,
                'amount'           => $topUpAmount,
                'module'           => 'investments',
                'transaction_type' => 'investment_topup',
                'remarks'          => "Top-up for investment '{$investment->account_number}'.",
            ]);

            $this->transactions->record(
                investment: $investment,
                type: 'top_up',
                amount: $topUpAmount,
                posting: $financialTransaction->posting,
                description: "Top-up via posting '{$financialTransaction->posting?->reference}'.",
            );

            $investment = $investment->refresh();

            $this->schedules->regenerateRemaining($investment);

            $investment->update([
                'pending_topup_amount'       => null,
                'pending_topup_requested_by' => null,
                'pending_topup_requested_at' => null,
                'metadata'                   => $this->appendTopUpToMetadata($investment, $topUpAmount, $principalBefore, $actor),
            ]);

            return $investment->refresh();
        });

        $this->audit->log(
            action: 'topup_approved',
            module: 'investments',
            auditable: $investment,
            after: ['top_up_amount' => $topUpAmount, 'current_principal' => $investment->current_principal],
            description: "Top-up of {$topUpAmount} for investment '{$investment->account_number}' was approved by '{$actor?->username}' and applied immediately.",
        );

        $this->notifications->notifyUser(
            $topUpRequester,
            'investment',
            $investment->id,
            'Top-up approved',
            "Your top-up of {$topUpAmount} for investment '{$investment->account_number}' was approved.",
        );

        return $investment;
    }

    public function rejectTopUp(Investment $investment, string $reason): Investment
    {
        if ($investment->pending_topup_amount === null) {
            throw ValidationException::withMessages([
                'status' => ['This investment has no top-up awaiting approval.'],
            ]);
        }

        $actor       = Auth::guard('user')->user();
        $topUpAmount = (string) $investment->pending_topup_amount;
        $topUpRequester = $investment->pendingTopupRequestedBy;

        $investment->update([
            'pending_topup_amount'       => null,
            'pending_topup_requested_by' => null,
            'pending_topup_requested_at' => null,
        ]);

        $investment->refresh();

        $this->audit->log(
            action: 'topup_rejected',
            module: 'investments',
            auditable: $investment,
            after: ['top_up_amount' => $topUpAmount, 'reason' => $reason],
            description: "Top-up of {$topUpAmount} for investment '{$investment->account_number}' was rejected by '{$actor?->username}': {$reason}",
        );

        $this->notifications->notifyUser(
            $topUpRequester,
            'investment',
            $investment->id,
            'Top-up rejected',
            "Your top-up of {$topUpAmount} for investment '{$investment->account_number}' was rejected: {$reason}",
        );

        return $investment;
    }

    // Only an active investment can be terminated — pending ones are cancelled via reject(),
    // and matured/liquidated/closed/rejected ones are already at a final state. Full current
    // principal is returned to the customer's source account, minus an early-liquidation
    // penalty (charged as a separate posting, not netted into a single amount, since the
    // posting engine's amount field can only carry one figure per leg) if the product's
    // early_liquidation_penalty_rate is set. Any still-open schedule installments are marked
    // 'cancelled' — they will never happen now.
    public function terminate(Investment $investment, string $reason): Investment
    {
        if ($investment->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Only active investments can be terminated.'],
            ]);
        }

        $product = InvestmentProduct::with('generalLedgerMappings')->findOrFail($investment->investment_product_id);

        if (! $product->allow_early_liquidation) {
            throw ValidationException::withMessages([
                'status' => ['This investment product does not allow early termination.'],
            ]);
        }

        $actor = Auth::guard('user')->user();

        $investment = DB::transaction(function () use ($investment, $product, $reason) {
            $fundingMapping = $product->generalLedgerMappings
                ->firstWhere('financial_account_type_name', InvestmentProductType::INVESTMENT_FUNDING->name);

            if (! $fundingMapping) {
                throw ValidationException::withMessages([
                    'investment_product_id' => ['This investment product has no Investment Funding GL mapping configured.'],
                ]);
            }

            $principal = (string) $investment->current_principal;

            // Return the full principal — debit the liability GL, credit the customer.
            $payoutTransaction = $this->postings->createAndApprove([
                'source_type'      => 'general_ledger',
                'source_id'        => $fundingMapping->general_ledger_id,
                'destination_type' => 'account',
                'destination_id'   => $investment->source_account_id,
                'amount'           => $principal,
                'module'           => 'investments',
                'transaction_type' => 'investment_early_liquidation_payout',
                'remarks'          => "Early liquidation payout for investment '{$investment->account_number}': {$reason}",
            ]);

            $this->transactions->record(
                investment: $investment,
                type: 'maturity_payout',
                amount: $principal,
                posting: $payoutTransaction->posting,
                description: "Principal returned via posting '{$payoutTransaction->posting?->reference}' (early termination).",
            );

            $investment = $investment->refresh();

            // Then claw back the penalty as a separate charge against the customer, if the
            // product has one configured — deducted from what they just received.
            // Read from the investment's own snapshot, not the live product — the penalty
            // rate is a locked-in term of this specific customer's deal (set at booking
            // time), same as elsewhere in this service; a later product edit must not
            // retroactively change what an existing investment is charged.
            $penaltyRate = (string) ($investment->early_liquidation_penalty_rate ?? '0');

            if (bccomp($penaltyRate, '0', 6) > 0) {
                $penaltyMapping = $product->generalLedgerMappings
                    ->firstWhere('financial_account_type_name', InvestmentProductType::EARLY_LIQUIDATION_PENALTY_INCOME->name);

                if (! $penaltyMapping) {
                    throw ValidationException::withMessages([
                        'investment_product_id' => ['This investment product has no Early Liquidation Penalty Income GL mapping configured.'],
                    ]);
                }

                $penaltyAmount = bcdiv(bcmul($principal, $penaltyRate, 6), '100', 6);

                if (bccomp($penaltyAmount, '0', 6) > 0) {
                    $penaltyTransaction = $this->postings->createAndApprove([
                        'source_type'      => 'account',
                        'source_id'        => $investment->source_account_id,
                        'destination_type' => 'general_ledger',
                        'destination_id'   => $penaltyMapping->general_ledger_id,
                        'amount'           => $penaltyAmount,
                        'module'           => 'investments',
                        'transaction_type' => 'investment_early_liquidation_penalty',
                        'remarks'          => "Early liquidation penalty for investment '{$investment->account_number}'.",
                    ]);

                    $this->transactions->record(
                        investment: $investment,
                        type: 'early_liquidation',
                        amount: $penaltyAmount,
                        posting: $penaltyTransaction->posting,
                        description: "Early liquidation penalty via posting '{$penaltyTransaction->posting?->reference}'.",
                    );

                    $investment = $investment->refresh();
                }
            }

            $latestVersion = (int) ($investment->schedules()->max('version') ?: 1);

            $investment->schedules()
                ->where('version', $latestVersion)
                ->whereIn('status', ['pending', 'partial'])
                ->update(['status' => 'cancelled']);

            $investment->update(['status' => 'liquidated']);

            return $investment->refresh();
        });

        $this->audit->log(
            action: 'terminated',
            module: 'investments',
            auditable: $investment,
            after: ['status' => $investment->status, 'reason' => $reason],
            description: "Investment '{$investment->account_number}' was terminated early by '{$actor?->username}': {$reason}",
        );

        return $investment;
    }

    // Appends a labeled entry ("first_topup", "second_topup", ...) to the investment's
    // metadata.top_ups list — a human-readable summary on top of the authoritative
    // investment_transactions ledger (already recorded separately by the 'top_up'
    // record() call above), so "list every top-up in order with who did what" is a
    // single-column read instead of a filtered query.
    //
    // Stored as a JSON *array* (not an object keyed by label) deliberately — MySQL's JSON
    // type does not reliably preserve key insertion order for objects on repeated
    // read-modify-write updates (confirmed: a 3-topup investment came back as
    // {first_topup, third_topup, second_topup}), but array element order is preserved.
    // The ordinal still appears, just as a "label" field's value instead of an object key.
    private function appendTopUpToMetadata(Investment $investment, string $amount, string $principalBefore, ?User $approver): array
    {
        $metadata = $investment->metadata ?? [];
        $topUps   = $metadata['top_ups'] ?? [];

        $requestedBy = $investment->pending_topup_requested_by
            ? User::find($investment->pending_topup_requested_by)?->username
            : null;

        $topUps[] = [
            'label'            => $this->ordinalTopUpKey(count($topUps) + 1),
            'amount'           => $amount,
            'principal_before' => $principalBefore,
            'principal_after'  => bcadd($principalBefore, $amount, 6),
            'requested_by'     => $requestedBy,
            'requested_at'     => $investment->pending_topup_requested_at?->toIso8601String(),
            'approved_by'      => $approver?->username,
            'approved_at'      => now()->toIso8601String(),
        ];

        $metadata['top_ups'] = $topUps;

        return $metadata;
    }

    private function ordinalTopUpKey(int $position): string
    {
        $words = [
            1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth',
            6 => 'sixth', 7 => 'seventh', 8 => 'eighth', 9 => 'ninth', 10 => 'tenth',
        ];

        return ($words[$position] ?? "{$position}th") . '_topup';
    }

    private function assertTopUpAllowed(Investment $investment, string $amount): InvestmentProduct
    {
        if ($investment->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Only active investments can be topped up.'],
            ]);
        }

        if ($investment->pending_topup_amount !== null) {
            throw ValidationException::withMessages([
                'amount' => ['This investment already has a top-up awaiting approval.'],
            ]);
        }

        $product = InvestmentProduct::findOrFail($investment->investment_product_id);

        if (! $product->allow_top_up) {
            throw ValidationException::withMessages([
                'amount' => ['This investment product does not allow top-ups.'],
            ]);
        }

        if (bccomp($amount, '0', 6) <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Top-up amount must be greater than zero.'],
            ]);
        }

        return $product;
    }

    private function assertBookingTermsAreValid(InvestmentProduct $product, string $principal, string $postingPeriod, int $tenure): void
    {
        if ($product->min_deposit_amount !== null && bccomp($principal, (string) $product->min_deposit_amount, 6) < 0) {
            throw ValidationException::withMessages([
                'principal_amount' => ['The minimum deposit for this product is ' . (string) $product->min_deposit_amount . '.'],
            ]);
        }

        if ($product->max_deposit_amount !== null && bccomp($principal, (string) $product->max_deposit_amount, 6) > 0) {
            throw ValidationException::withMessages([
                'principal_amount' => ['The maximum deposit for this product is ' . (string) $product->max_deposit_amount . '.'],
            ]);
        }

        if ($tenure < $product->min_tenure_days || $tenure > $product->max_tenure_days) {
            throw ValidationException::withMessages([
                'tenure' => ["Tenure for this product must be between {$product->min_tenure_days} and {$product->max_tenure_days} days."],
            ]);
        }

        $allowedPostingPeriods = $product->allowed_interest_posting_periods ?? [];

        if (! in_array($postingPeriod, $allowedPostingPeriods, true)) {
            throw ValidationException::withMessages([
                'interest_posting_period' => ["'{$postingPeriod}' is not offered by this investment product."],
            ]);
        }
    }

    // Collisions are structurally unlikely (id-based lock+sequence), but if two opens
    // race on an empty prefix they can compute the same number; account_number is
    // DB-unique, so retry on that specific constraint violation.
    // generateUniqueAccountNumber()'s lockForUpdate() has nothing to lock when there are
    // zero existing investments, so two concurrent "first investment" opens can compute the
    // same number. account_number is DB-unique, so that collision surfaces as a
    // QueryException at INSERT rather than a silent duplicate — retry the whole open a few
    // times on that specific error instead of failing the request.
    private function transactionWithAccountNumberRetry(callable $callback): Investment
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

    // Must be called inside a DB::transaction so lockForUpdate() actually holds the lock until commit.
    private function generateUniqueAccountNumber(): string
    {
        $lastNumber = Investment::where('account_number', 'like', self::ACCOUNT_NUMBER_PREFIX . '%')
            ->lockForUpdate()
            ->orderByDesc('account_number')
            ->value('account_number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, 1)) + 1 : 1;

        return self::ACCOUNT_NUMBER_PREFIX . str_pad((string) $nextSequence, 9, '0', STR_PAD_LEFT);
    }
}
