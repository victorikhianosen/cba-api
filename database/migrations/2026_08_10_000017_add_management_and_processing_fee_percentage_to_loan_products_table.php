<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The only two configurable fee types — resolved live (percentage of principal,
    // charged at disbursement) by LoanFeeService::chargeDisbursementFees() against the
    // product's own MANAGEMENT_FEE_INCOME/PROCESSING_FEE_INCOME GL roles.
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('management_fee_percentage', 5, 2)->nullable()->after('interest_rate');
            $table->decimal('processing_fee_percentage', 5, 2)->nullable()->after('management_fee_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['management_fee_percentage', 'processing_fee_percentage']);
        });
    }
};
