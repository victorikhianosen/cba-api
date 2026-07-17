<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 45)->unique();
            $table->text('description')->nullable();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->smallInteger('currency_digits')->default(2);

            $table->decimal('interest_rate', 19, 6)->default(0);
            $table->decimal('min_nominal_interest_rate_per_period', 19, 6)->nullable();
            $table->decimal('max_nominal_interest_rate_per_period', 19, 6)->nullable();
            $table->enum('interest_rate_frequency_type', ['per_day', 'per_week', 'per_month', 'per_year'])->nullable();
            $table->enum('interest_method', ['declining_balance', 'flat'])->nullable();
            $table->enum('interest_calculation_period_type', ['daily', 'same_as_repayment_period'])->nullable();
            $table->enum('interest_calculation_days_in_year', ['360', '365'])->default('365');

            $table->decimal('min_principal_amount', 19, 6)->nullable();
            $table->decimal('max_principal_amount', 19, 6)->nullable();
            $table->decimal('default_principal_amount', 19, 6)->nullable();

            $table->unsignedInteger('repayment_every')->nullable();
            $table->enum('repayment_frequency_type', ['days', 'weeks', 'months'])->nullable();
            $table->unsignedInteger('number_of_repayments')->nullable();
            $table->unsignedInteger('min_number_of_repayments')->nullable();
            $table->unsignedInteger('max_number_of_repayments')->nullable();
            $table->enum('amortization_method', ['equal_installments', 'equal_principal'])->nullable();

            $table->unsignedInteger('grace_on_principal_periods')->default(0);
            $table->unsignedInteger('grace_on_interest_periods')->default(0);
            $table->unsignedInteger('grace_interest_free_periods')->default(0);
            $table->unsignedInteger('grace_on_arrears_ageing')->default(0);
            $table->decimal('arrears_tolerance_amount', 19, 6)->default(0);
            $table->unsignedInteger('overdue_days_for_npa')->nullable();
            $table->enum('days_in_month_type', ['actual', 'thirty'])->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
