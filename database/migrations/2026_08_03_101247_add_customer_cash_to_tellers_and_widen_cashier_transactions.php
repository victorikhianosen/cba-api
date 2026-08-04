<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Adds customer-facing cash-in/cash-out support to the Teller module: a second,
// expense-classified GL per teller (deposits/withdrawals need opposite polarity from the
// liability-classified vault-transfer control GL — see the "Teller Cash Deposit /
// Withdrawal" plan for the full reasoning), plus withdrawal maker-checker support on
// cashier_transactions.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tellers', function (Blueprint $table) {
            $table->foreignId('customer_cash_general_ledger_id')
                ->nullable()
                ->after('general_ledger_id')
                ->constrained('general_ledgers')
                ->restrictOnDelete();
        });

        // Laravel's fluent schema builder can't widen an existing enum column without
        // doctrine/dbal, which isn't a dependency here — raw SQL instead.
        DB::statement("ALTER TABLE cashier_transactions MODIFY type ENUM('cash_buy', 'cash_sell', 'variance_adjustment', 'cash_deposit', 'cash_withdrawal') NOT NULL");
        DB::statement("ALTER TABLE cashier_transactions MODIFY counterparty_type ENUM('vault', 'teller', 'account') NULL");

        Schema::table('cashier_transactions', function (Blueprint $table) {
            // Every existing transaction type here posts immediately — only a withdrawal
            // request starts life as 'pending', so 'approved' is the correct default for
            // rows that were never subject to a maker-checker step.
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('type');
            $table->foreignId('requested_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_transactions', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropColumn(['status', 'requested_by']);
        });

        DB::statement("ALTER TABLE cashier_transactions MODIFY counterparty_type ENUM('vault', 'teller') NULL");
        DB::statement("ALTER TABLE cashier_transactions MODIFY type ENUM('cash_buy', 'cash_sell', 'variance_adjustment') NOT NULL");

        Schema::table('tellers', function (Blueprint $table) {
            $table->dropForeign(['customer_cash_general_ledger_id']);
            $table->dropColumn('customer_cash_general_ledger_id');
        });
    }
};
