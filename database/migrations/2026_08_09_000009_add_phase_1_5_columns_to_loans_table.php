<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen the fixed 5-value enum to a plain string so npl_classification can hold
        // any active loan_classification_rules.name, including custom rules an admin adds
        // later — not just the 5 CBN defaults baked in when this column was first created.
        DB::statement('ALTER TABLE loans MODIFY npl_classification VARCHAR(255) NOT NULL DEFAULT \'performing\'');

        Schema::table('loans', function ($table) {
            // Interest suspension — once a loan is non-performing, interest stops being
            // recognized as income until actually collected (regulatory-standard accrual
            // accounting). Phase 2's accrual cron is what will actually flip these; this
            // phase only adds the columns + LoanTransactionService support.
            $table->boolean('interest_suspended')->default(false)->after('npl_classified_at');
            $table->timestamp('interest_suspended_at')->nullable()->after('interest_suspended');
            $table->decimal('suspended_interest_balance', 19, 2)->default(0)->after('interest_suspended_at');

            // Every other module already carries these — Loan was missing both, blocking
            // any future by-branch/by-officer performance view.
            $table->foreignId('branch_id')->nullable()->after('customer_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('loan_officer_id')->nullable()->after('branch_id')->constrained('account_officers')->nullOnDelete();

            $table->index('branch_id');
            $table->index('loan_officer_id');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function ($table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('loan_officer_id');
            $table->dropColumn(['interest_suspended', 'interest_suspended_at', 'suspended_interest_balance']);
        });

        DB::statement('ALTER TABLE loans MODIFY npl_classification ENUM(\'performing\',\'watch\',\'substandard\',\'doubtful\',\'lost\') NOT NULL DEFAULT \'performing\'');
    }
};
