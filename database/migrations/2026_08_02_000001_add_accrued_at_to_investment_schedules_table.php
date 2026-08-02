<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Marks the moment the interest_accrual GL entry (Dr Interest Expense / Cr Interest
    // Payable) was posted for this installment — distinct from paid_date, which only gets
    // set once the money actually reaches the customer. Nullable + indexed so the batch
    // job in App\Console\Commands\ProcessInvestmentInterest can select "not yet accrued"
    // and "accrued but not yet paid" with a plain WHERE, no derived query against
    // investment_transactions needed at scale.
    public function up(): void
    {
        Schema::table('investment_schedules', function (Blueprint $table) {
            $table->timestamp('accrued_at')->nullable()->after('status');
            $table->index('accrued_at');
        });
    }

    public function down(): void
    {
        Schema::table('investment_schedules', function (Blueprint $table) {
            $table->dropIndex(['accrued_at']);
            $table->dropColumn('accrued_at');
        });
    }
};
