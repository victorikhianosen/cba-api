<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            // Penal interest rate (% per annum), accrued daily against the overdue
            // portion of a missed installment — distinct from the loan's normal
            // interest_rate. Defaults to 0 (no penalty) rather than nullable, since every
            // repayment/overdue calculation treats "no rate configured" the same as "0%".
            $table->decimal('penalty_interest_rate', 19, 2)->default(0)->after('overdue_days_for_npa');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('penalty_interest_rate');
        });
    }
};
