<?php

declare(strict_types=1);

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanProduct;
use Carbon\Carbon;

// Generates the amortization schedule once a loan's term actually starts (called from
// LoanService::approve() right after disbursement posts). Unlike
// InvestmentScheduleService (flat simple-interest, static principal), this does real
// amortization — principal genuinely declines installment over installment — so every
// calculation runs on bcmath throughout, never float/round(), since the iterative EMI
// formula is sensitive to rounding drift compounding over many periods.
//
// calculate() is the pure, side-effect-free core — generate() persists its output onto a
// real Loan, preview() returns it as-is before anyone commits to booking one.
class LoanScheduleService
{
    private const BC_SCALE = 12;

    public function generate(Loan $loan): void
    {
        $rows = $this->calculate(
            principal: (string) $loan->principal_amount,
            annualRatePercent: (string) $loan->interest_rate,
            interestRateFrequencyType: $loan->interest_rate_frequency_type,
            repaymentEvery: (int) $loan->repayment_every,
            repaymentFrequencyType: $loan->repayment_frequency_type,
            duration: (int) $loan->duration,
            interestMethod: $loan->interest_method,
            startDate: Carbon::parse($loan->start_date),
        );

        foreach ($rows as $row) {
            $loan->schedules()->create($row);
        }
    }

    // Phase 2 restructuring target: supersede the still-open installments of the latest
    // version, generate a fresh version from today on the current outstanding balance and
    // (possibly) new terms. Mirrors InvestmentScheduleService::regenerateRemaining()
    // exactly, adapted for real amortization instead of static-principal simple interest.
    public function regenerateRemaining(Loan $loan, ?int $duration = null, ?int $repaymentEvery = null, ?string $interestRate = null): void
    {
        $latestVersion = (int) ($loan->schedules()->max('version') ?: 1);
        $newVersion    = $latestVersion + 1;

        $loan->schedules()
            ->where('version', $latestVersion)
            ->whereIn('status', ['pending', 'partial'])
            ->update(['superseded_at' => now(), 'status' => 'superseded']);

        $rows = $this->calculate(
            principal: (string) $loan->outstanding_principal,
            annualRatePercent: (string) ($interestRate ?? $loan->interest_rate),
            interestRateFrequencyType: $loan->interest_rate_frequency_type,
            repaymentEvery: $repaymentEvery ?? (int) $loan->repayment_every,
            repaymentFrequencyType: $loan->repayment_frequency_type,
            duration: $duration ?? (int) $loan->duration,
            interestMethod: $loan->interest_method,
            startDate: Carbon::today(),
        );

        foreach ($rows as $row) {
            $row['version'] = $newVersion;
            $loan->schedules()->create($row);
        }
    }

    // Mirrors regenerateRemaining() exactly (same calculate() call, same defaulting of
    // unset params to the loan's current values) but writes nothing — used to show a
    // reschedule/restructure/top-up's effect before anyone commits to requesting it.
    // $principalOverride lets a caller preview against a hypothetical principal (e.g.
    // Top-Up's outstanding + topup_amount, or Restructure's post-capitalization/waiver
    // figure) instead of the loan's current outstanding_principal.
    public function previewRemaining(
        Loan $loan,
        ?int $duration = null,
        ?int $repaymentEvery = null,
        ?string $interestRate = null,
        ?string $principalOverride = null,
    ): array {
        $rows = $this->calculate(
            principal: $principalOverride ?? (string) $loan->outstanding_principal,
            annualRatePercent: (string) ($interestRate ?? $loan->interest_rate),
            interestRateFrequencyType: $loan->interest_rate_frequency_type,
            repaymentEvery: $repaymentEvery ?? (int) $loan->repayment_every,
            repaymentFrequencyType: $loan->repayment_frequency_type,
            duration: $duration ?? (int) $loan->duration,
            interestMethod: $loan->interest_method,
            startDate: Carbon::today(),
        );

        $totalInterest = array_reduce($rows, fn ($carry, $row) => bcadd($carry, (string) $row['interest_due'], 2), '0.00');

        return [
            'maturity_date'   => end($rows)['due_date'],
            'total_interest'  => $totalInterest,
            'installments'    => $rows,
        ];
    }

    /**
     * Computes the same schedule generate() would persist, for a product + hypothetical
     * terms that may not belong to a saved loan yet — nothing is written. Used to show the
     * projected repayment schedule before anyone commits to booking it.
     *
     * @return array{start_date: string, maturity_date: string, total_interest: string, installment_amount: string, installments: array<int, array<string, mixed>>}
     */
    public function preview(LoanProduct $product, string $principal, int $duration, ?Carbon $startDate = null): array
    {
        $startDate ??= Carbon::today();

        $rows = $this->calculate(
            principal: $principal,
            annualRatePercent: (string) $product->interest_rate,
            interestRateFrequencyType: $product->interest_rate_frequency_type,
            repaymentEvery: (int) $product->repayment_every,
            repaymentFrequencyType: $product->repayment_frequency_type,
            duration: $duration,
            interestMethod: $product->interest_method,
            startDate: $startDate,
        );

        $totalInterest = array_reduce($rows, fn ($carry, $row) => bcadd($carry, (string) $row['interest_due'], 2), '0.00');

        // Always a fixed figure now — Equal Installments is the only amortization
        // method left, so the installment amount is constant across the schedule.
        $installmentAmount = bcadd((string) $rows[0]['principal_due'], (string) $rows[0]['interest_due'], 2);

        return [
            'start_date'          => $startDate->toDateString(),
            'maturity_date'       => end($rows)['due_date'],
            'total_interest'      => $totalInterest,
            'installment_amount'  => $installmentAmount,
            'installments'        => $rows,
        ];
    }

    // Same output shape as preview(), but reads the loan's own already-snapshotted terms
    // (interest_rate, repayment_every, ...) instead of a product's live ones — used to
    // show an approver exactly the schedule generate() will persist at approval, which
    // may differ from what the product currently offers if it's been edited since booking.
    public function previewForLoan(Loan $loan): array
    {
        $startDate = Carbon::today();

        $rows = $this->calculate(
            principal: (string) $loan->principal_amount,
            annualRatePercent: (string) $loan->interest_rate,
            interestRateFrequencyType: $loan->interest_rate_frequency_type,
            repaymentEvery: (int) $loan->repayment_every,
            repaymentFrequencyType: $loan->repayment_frequency_type,
            duration: (int) $loan->duration,
            interestMethod: $loan->interest_method,
            startDate: $startDate,
        );

        $totalInterest = array_reduce($rows, fn ($carry, $row) => bcadd($carry, (string) $row['interest_due'], 2), '0.00');
        $installmentAmount = bcadd((string) $rows[0]['principal_due'], (string) $rows[0]['interest_due'], 2);

        return [
            'start_date'          => $startDate->toDateString(),
            'maturity_date'       => end($rows)['due_date'],
            'total_interest'      => $totalInterest,
            'installment_amount'  => $installmentAmount,
            'installments'        => $rows,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function calculate(
        string $principal,
        string $annualRatePercent,
        string $interestRateFrequencyType,
        int $repaymentEvery,
        string $repaymentFrequencyType,
        int $duration,
        string $interestMethod,
        Carbon $startDate,
    ): array {
        $annualRate     = $this->toAnnualRate($annualRatePercent, $interestRateFrequencyType);
        $periodsPerYear = $this->periodsPerYear($repaymentFrequencyType, $repaymentEvery);
        // Periodic rate as a fraction (not a percent) — e.g. 24% p.a. / 12 periods/yr = 0.02.
        $periodicRate = bcdiv(bcdiv($annualRate, '100', self::BC_SCALE), $periodsPerYear, self::BC_SCALE);

        $dueDates = $this->buildDueDates($startDate, $repaymentEvery, $repaymentFrequencyType, $duration);

        if ($interestMethod === 'flat') {
            return $this->calculateFlat($principal, $annualRate, $startDate, $dueDates);
        }

        return $this->calculateEqualInstallments($principal, $periodicRate, $annualRate, $startDate, $dueDates);
    }

    // Flat interest: interest is charged on the ORIGINAL principal for every period, not
    // the declining balance — total_interest = principal * periodicRate * n, split evenly
    // across installments (constant interest per period). Principal is likewise split
    // evenly (principal / n) since a constant interest component makes the
    // equal-installment and equal-principal amortization patterns converge to the same
    // thing under flat interest — there's no separate EMI formula for flat loans, just an
    // equal split of both components. Produces materially lower total interest than the
    // declining-balance methods above for the same nominal rate, because interest is never
    // recalculated on a shrinking balance.
    private function calculateFlat(
        string $principal,
        string $annualRate,
        Carbon $startDate,
        array $dueDates,
    ): array {
        $n = count($dueDates);
        $principalPerPeriod = bcdiv($principal, (string) $n, 2);

        $rows        = [];
        $opening     = $principal;
        $periodStart = $startDate->copy();
        $totalInterestAllocated = '0.00';

        // Interest for a single period, always computed against the ORIGINAL principal
        // (never the declining $opening), then that same figure is reused for every period.
        $flatPeriodInterest = $this->periodInterest($principal, $annualRate, $startDate, $dueDates[0] ?? $startDate);

        foreach ($dueDates as $index => $dueDate) {
            $isLast = $index === $n - 1;

            $principalDue = $isLast ? $opening : $principalPerPeriod;
            $closing      = $isLast ? '0.00' : bcsub($opening, $principalDue, 2);

            // Last installment absorbs any rounding remainder so the schedule sums exactly
            // to the total flat interest computed once up front.
            $totalFlatInterest = bcmul($flatPeriodInterest, (string) $n, 2);
            $interestDue = $isLast
                ? bcsub($totalFlatInterest, $totalInterestAllocated, 2)
                : $flatPeriodInterest;

            $rows[] = [
                'installment_number'         => $index + 1,
                'due_date'                   => $dueDate->toDateString(),
                'opening_principal_balance'  => $opening,
                'principal_due'               => $principalDue,
                'interest_due'                => $interestDue,
                'closing_principal_balance'  => $closing,
                'status'                      => 'pending',
            ];

            $opening     = $closing;
            $periodStart = $dueDate->copy();
            $totalInterestAllocated = bcadd($totalInterestAllocated, $interestDue, 2);
        }

        return $rows;
    }

    // EMI = P * r * (1+r)^n / ((1+r)^n - 1). bcmath has no native bcpow for non-integer
    // precision needs here, so (1+r)^n is built via a plain multiplication loop.
    private function calculateEqualInstallments(
        string $principal,
        string $periodicRate,
        string $annualRate,
        Carbon $startDate,
        array $dueDates,
    ): array {
        $n = count($dueDates);

        $factor = '1';
        for ($i = 0; $i < $n; $i++) {
            $factor = bcmul($factor, bcadd('1', $periodicRate, self::BC_SCALE), self::BC_SCALE);
        }

        $numerator   = bcmul(bcmul($principal, $periodicRate, self::BC_SCALE), $factor, self::BC_SCALE);
        $denominator = bcsub($factor, '1', self::BC_SCALE);
        $installment = bccomp($denominator, '0', self::BC_SCALE) === 0
            ? bcdiv($principal, (string) $n, 2) // zero-rate edge case: straight division, no interest
            : bcdiv($numerator, $denominator, 2);

        $rows      = [];
        $opening   = $principal;
        $periodStart = $startDate->copy();

        foreach ($dueDates as $index => $dueDate) {
            $isLast = $index === $n - 1;

            $interestDue = $this->periodInterest($opening, $annualRate, $periodStart, $dueDate);
            $principalDue = $isLast ? $opening : bcsub($installment, $interestDue, 2);
            // Guard against a rounding-driven negative on the very last period.
            if (bccomp($principalDue, $opening, 2) > 0) {
                $principalDue = $opening;
            }
            $closing = bcsub($opening, $principalDue, 2);
            if ($isLast) {
                $closing = '0.00';
            }

            $rows[] = [
                'installment_number'         => $index + 1,
                'due_date'                   => $dueDate->toDateString(),
                'opening_principal_balance'  => $opening,
                'principal_due'               => $principalDue,
                'interest_due'                => $interestDue,
                'closing_principal_balance'  => $closing,
                'status'                      => 'pending',
            ];

            $opening     = $closing;
            $periodStart = $dueDate->copy();
        }

        return $rows;
    }

    // Always real calendar days per period, over a 365-day year — both used to be
    // per-product configurable (Interest Calculation Period, Days in Year), now
    // hardcoded since every product used the same values in practice.
    private function periodInterest(
        string $openingBalance,
        string $annualRate,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): string {
        $days      = (string) $periodStart->diffInDays($periodEnd);
        $dailyRate = bcdiv(bcdiv($annualRate, '100', self::BC_SCALE), '365', self::BC_SCALE);

        return bcmul(bcmul($openingBalance, $dailyRate, self::BC_SCALE), $days, 2);
    }

    private function toAnnualRate(string $rate, string $frequencyType): string
    {
        return match ($frequencyType) {
            'per_year'  => $rate,
            'per_month' => bcmul($rate, '12', self::BC_SCALE),
            'per_week'  => bcmul($rate, '52', self::BC_SCALE),
            'per_day'   => bcmul($rate, '365', self::BC_SCALE),
            default     => $rate,
        };
    }

    private function periodsPerYear(string $repaymentFrequencyType, int $repaymentEvery): string
    {
        $base = match ($repaymentFrequencyType) {
            'days'   => '365',
            'weeks'  => '52',
            'months' => '12',
            default  => '12',
        };

        return bcdiv($base, (string) max(1, $repaymentEvery), self::BC_SCALE);
    }

    /** @return Carbon[] */
    private function buildDueDates(Carbon $startDate, int $repaymentEvery, string $repaymentFrequencyType, int $duration): array
    {
        $dates   = [];
        $current = $startDate->copy();

        for ($i = 1; $i <= $duration; $i++) {
            $current = match ($repaymentFrequencyType) {
                'days'   => $current->copy()->addDays($repaymentEvery),
                'weeks'  => $current->copy()->addWeeks($repaymentEvery),
                'months' => $current->copy()->addMonths($repaymentEvery),
                default  => $current->copy()->addMonths($repaymentEvery),
            };

            $dates[] = $current->copy();
        }

        return $dates;
    }
}
