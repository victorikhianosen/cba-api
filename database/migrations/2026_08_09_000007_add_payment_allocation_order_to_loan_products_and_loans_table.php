<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // JSON array of the 4 bucket names in the order a repayment should be applied,
        // e.g. ["penalty","fee","interest","principal"] — nullable on the product (a
        // sensible default is applied in code when absent), always populated on the loan
        // itself once booked (snapshotted from the product, same as every other term).
        Schema::table('loan_products', function (Blueprint $table) {
            $table->json('payment_allocation_order')->nullable()->after('penalty_interest_rate');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->json('payment_allocation_order')->nullable()->after('penalty_interest_rate');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('payment_allocation_order');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('payment_allocation_order');
        });
    }
};
