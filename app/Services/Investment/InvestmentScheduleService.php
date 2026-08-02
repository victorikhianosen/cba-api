<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment;
use App\Models\InvestmentProduct;
use Carbon\Carbon;

// Generates the forward-looking interest schedule once an investment's term actually
// starts (called directly from InvestmentService::approve() once funding posts).
// Simple interest only — the product has no compounding field.
//
// calculate() is the pure, side-effect-free core — generate() persists its output onto a
// real Investment, preview() returns it as-is for a not-yet-created one so the UI can show
// the projected schedule before anyone commits to opening the investment.
class InvestmentScheduleService
{
    private const PERIODS_PER_YEAR = [
        'daily'       => 365,
        'weekly'      => 52,
        'monthly'     => 12,
        'quarterly'   => 4,
        'bi_annually' => 2,
        'annually'    => 1,
    ];

    public function generate(Investment $investment): void
    {
        $rows = $this->calculate(
            principal: (float) $investment->principal_amount,
            interestRate: (float) $investment->interest_rate,
            tenureDays: $investment->tenure,
            postingPeriod: $investment->interest_posting_period,
            startDate: Carbon::parse($investment->start_date),
        );

        foreach ($rows as $row) {
            $investment->schedules()->create($row);
        }
    }

    // Called after a top-up is approved (current_principal has already grown). Freezes the
    // still-open ("pending"/"partial") installments of the latest version in place —
    // superseded_at is set, not deleted, so the pre-top-up projection stays visible for a
    // side-by-side comparison — then generates a fresh version's worth of installments from
    // today to maturity_date, recalculated on the new, larger principal. Already-paid
    // installments are untouched either way; they already happened at the old principal.
    public function regenerateRemaining(Investment $investment): void
    {
        $latestVersion = (int) ($investment->schedules()->max('version') ?: 1);
        $newVersion    = $latestVersion + 1;

        $investment->schedules()
            ->where('version', $latestVersion)
            ->whereIn('status', ['pending', 'partial'])
            ->update(['superseded_at' => now(), 'status' => 'superseded']);

        $today         = Carbon::today();
        $maturityDate  = Carbon::parse($investment->maturity_date);
        $remainingDays = max(1, $today->diffInDays($maturityDate));

        $rows = $this->calculate(
            principal: (float) $investment->current_principal,
            interestRate: (float) $investment->interest_rate,
            tenureDays: (int) $remainingDays,
            postingPeriod: $investment->interest_posting_period,
            startDate: $today,
        );

        foreach ($rows as $row) {
            $row['version'] = $newVersion;
            $investment->schedules()->create($row);
        }
    }

    /**
     * Computes the same schedule generate() would persist, but for a product + hypothetical
     * terms that may not even belong to a saved investment yet — nothing is written to the
     * database. Used to show a customer/admin the projected schedule before they book it.
     *
     * @return array{maturity_date: string, total_interest: string, installments: array<int, array<string, mixed>>}
     */
    public function preview(InvestmentProduct $product, float $principal, string $postingPeriod, int $tenureDays, ?Carbon $startDate = null): array
    {
        $startDate ??= Carbon::today();

        $rows = $this->calculate(
            principal: $principal,
            interestRate: (float) $product->interest_rate,
            tenureDays: $tenureDays,
            postingPeriod: $postingPeriod,
            startDate: $startDate,
        );

        return [
            'start_date'     => $startDate->toDateString(),
            'maturity_date'  => $startDate->copy()->addDays($tenureDays)->toDateString(),
            'total_interest' => (string) round(array_sum(array_column($rows, 'expected_interest_amount')), 6),
            'installments'   => $rows,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function calculate(float $principal, float $interestRate, int $tenureDays, string $postingPeriod, Carbon $startDate): array
    {
        $maturityDate  = $startDate->copy()->addDays($tenureDays);
        $totalYears    = $tenureDays / 365;
        $totalInterest = round($principal * ($interestRate / 100) * $totalYears, 6);

        $installmentDates = $this->buildInstallmentDates($postingPeriod, $startDate, $maturityDate, $totalYears);

        $perInstallmentInterest = round($totalInterest / count($installmentDates), 6);

        $rows = [];

        foreach ($installmentDates as $index => $dueDate) {
            $isLast = $index === array_key_last($installmentDates);

            $rows[] = [
                'installment_number'        => $index + 1,
                'due_date'                  => $dueDate->toDateString(),
                'opening_principal_balance' => $principal,
                // Last installment absorbs any rounding remainder so the schedule sums exactly to totalInterest.
                'expected_interest_amount'  => $isLast
                    ? round($totalInterest - ($perInstallmentInterest * (count($installmentDates) - 1)), 6)
                    : $perInstallmentInterest,
                'closing_principal_balance' => $principal,
                'status'                    => 'pending',
            ];
        }

        return $rows;
    }

    /** @return Carbon[] */
    private function buildInstallmentDates(string $postingPeriod, Carbon $startDate, Carbon $maturityDate, float $totalYears): array
    {
        if ($postingPeriod === 'upfront') {
            return [$startDate->copy()];
        }

        if ($postingPeriod === 'bullet') {
            return [$maturityDate->copy()];
        }

        $periodsPerYear = self::PERIODS_PER_YEAR[$postingPeriod] ?? 1;
        $installmentCount = max(1, (int) round($totalYears * $periodsPerYear));

        $dates = [];
        $intervalDays = $startDate->diffInDays($maturityDate) / $installmentCount;

        for ($i = 1; $i <= $installmentCount; $i++) {
            $dueDate = $i === $installmentCount
                ? $maturityDate->copy()
                : $startDate->copy()->addDays((int) round($intervalDays * $i));

            $dates[] = $dueDate;
        }

        return $dates;
    }
}
