<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // A top-up request doesn't change the investment's main status (it's still an
            // active, funded investment the whole time) — these three columns are the
            // "is there a top-up awaiting approval" state instead. Cleared back to null once
            // approved or rejected.
            $table->decimal('pending_topup_amount', 19, 6)->nullable()->after('total_top_up_amount');
            $table->foreignId('pending_topup_requested_by')->nullable()->after('pending_topup_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('pending_topup_requested_at')->nullable()->after('pending_topup_requested_by');
        });

        Schema::table('investment_schedules', function (Blueprint $table) {
            // Bumped whenever a top-up regenerates the remaining (not-yet-paid) installments
            // on the new, larger principal — old rows are kept (superseded_at set), not
            // deleted, so the UI can show the pre-top-up and post-top-up schedules side by
            // side instead of losing the original projection.
            $table->unsignedInteger('version')->default(1)->after('installment_number');
            $table->timestamp('superseded_at')->nullable()->after('status');
            $table->index('version');

            // installment_number alone is no longer unique per investment now that a
            // top-up can produce a second "installment #1" under a new version. The new
            // unique is added *before* dropping the old one — investment_id alone still
            // needs an index for its own FK constraint the whole time, and MySQL won't let
            // you drop the only index covering an FK column out from under it. Named
            // explicitly since the auto-generated 3-column name exceeds MySQL's 64-char
            // identifier limit.
            $table->unique(['investment_id', 'version', 'installment_number'], 'inv_schedules_id_version_installment_unique');
            $table->dropUnique(['investment_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::table('investment_schedules', function (Blueprint $table) {
            $table->unique(['investment_id', 'installment_number']);
            $table->dropUnique('inv_schedules_id_version_installment_unique');
            $table->dropIndex(['version']);
            $table->dropColumn(['version', 'superseded_at']);
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_topup_requested_by');
            $table->dropColumn(['pending_topup_amount', 'pending_topup_requested_at']);
        });
    }
};
