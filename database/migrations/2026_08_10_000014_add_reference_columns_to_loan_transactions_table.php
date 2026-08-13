<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_transactions', function (Blueprint $table) {
            // reference: this loan_transaction row's own identifier, always set.
            // transaction_reference: the underlying FinancialTransaction's reference, when
            // this entry is backed by a real posting (marker-only types like
            // reschedule/restructure have no posting, so this stays null for those).
            $table->string('reference')->nullable()->unique()->after('loan_id');
            $table->string('transaction_reference')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('loan_transactions', function (Blueprint $table) {
            $table->dropColumn(['reference', 'transaction_reference']);
        });
    }
};
