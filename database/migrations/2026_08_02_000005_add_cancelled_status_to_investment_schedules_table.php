<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Distinct from 'superseded' (replaced by a newer schedule version after a top-up) —
    // 'cancelled' is for installments that will simply never happen because the investment
    // was terminated early, not because a new version replaced them.
    public function up(): void
    {
        DB::statement("ALTER TABLE investment_schedules MODIFY status ENUM('pending', 'paid', 'partial', 'missed', 'superseded', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('investment_schedules')->where('status', 'cancelled')->update(['status' => 'missed']);
        DB::statement("ALTER TABLE investment_schedules MODIFY status ENUM('pending', 'paid', 'partial', 'missed', 'superseded') NOT NULL DEFAULT 'pending'");
    }
};
