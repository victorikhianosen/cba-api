<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A real, separate review stage before approval — the loan now moves
    // pending -> reviewed -> active instead of pending -> active directly, so a second,
    // distinct user must review it before a third, distinct user can approve+disburse
    // it (see LoanService::review()/approve()).
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY status ENUM('pending', 'reviewed', 'active', 'rejected', 'closed', 'written_off') NOT NULL DEFAULT 'pending'");

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });

        DB::statement("ALTER TABLE loans MODIFY status ENUM('pending', 'active', 'rejected', 'closed', 'written_off') NOT NULL DEFAULT 'pending'");
    }
};
