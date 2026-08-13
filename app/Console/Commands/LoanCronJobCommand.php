<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Loan\LoanCronJobService;
use Illuminate\Console\Command;

// No business logic lives here on purpose — every loan-related scheduled job's actual
// work belongs in LoanCronJobService; this command only parses options and formats
// output.
class LoanCronJobCommand extends Command
{
    protected $signature = 'loans:process-overdue {--dry-run : Report what would happen without posting anything}';

    protected $description = 'Accrue daily penalty interest on overdue loan installments and recompute NPL classification.';

    public function __construct(
        private LoanCronJobService $processor,
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
        $this->info("Accrued: {$result['accrued']}, Reclassified: {$result['reclassified']}, Failed: {$result['failed']}{$suffix}");

        return self::SUCCESS;
    }
}
