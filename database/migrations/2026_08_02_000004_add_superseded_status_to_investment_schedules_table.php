<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Widens the status enum so a frozen (superseded) installment can be told apart from a
    // real, still-open pending one — previously a superseded row kept status='pending'
    // forever, which read as actionable when it no longer was.
    public function up(): void
    {
        DB::statement("ALTER TABLE investment_schedules MODIFY status ENUM('pending', 'paid', 'partial', 'missed', 'superseded') NOT NULL DEFAULT 'pending'");

        // Backfill: any row already superseded_at-stamped but still showing 'pending' from
        // before this migration existed should reflect the new status too.
        DB::table('investment_schedules')
            ->whereNotNull('superseded_at')
            ->whereIn('status', ['pending', 'partial'])
            ->update(['status' => 'superseded']);
    }

    public function down(): void
    {
        DB::table('investment_schedules')->where('status', 'superseded')->update(['status' => 'pending']);
        DB::statement("ALTER TABLE investment_schedules MODIFY status ENUM('pending', 'paid', 'partial', 'missed') NOT NULL DEFAULT 'pending'");
    }
};
