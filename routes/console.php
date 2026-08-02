<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Batch-accrues and pays out interest for every investment schedule installment that's
// fallen due. See App\Console\Commands\ProcessInvestmentInterest — withoutOverlapping()
// guards against a slow run still going when the next day's run fires.
Schedule::command('investments:process-interest')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer();
