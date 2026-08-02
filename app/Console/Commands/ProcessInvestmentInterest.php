<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Investment\InvestmentInterestProcessingService;
use Illuminate\Console\Command;

// Thin cron entry point — all the actual accrual/payout logic lives in
// InvestmentInterestProcessingService so it's directly unit-testable and callable from
// anywhere else that might need it, not just artisan. This class only wires up the
// scheduler-facing bits: the --dry-run flag and printing the result.
class ProcessInvestmentInterest extends Command
{
    protected $signature = 'investments:process-interest {--dry-run : Report what would happen without posting anything}';

    protected $description = 'Accrue and disburse interest for every investment schedule installment that has fallen due.';

    public function __construct(
        private InvestmentInterestProcessingService $processor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = $this->processor->process($dryRun);

        foreach ($result['dry_run_lines'] as $line) {
            $this->line($line);
        }

        $suffix = $dryRun ? ' (dry run — nothing was posted)' : '';
        $this->info("Accrued: {$result['accrued']}, Paid: {$result['paid']}, Failed: {$result['failed']}{$suffix}");

        return self::SUCCESS;
    }
}
