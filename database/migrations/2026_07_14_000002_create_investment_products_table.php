<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 45)->unique();
            $table->text('description')->nullable();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->smallInteger('currency_digits')->default(2);

            $table->decimal('interest_rate', 19, 6)->default(0);
            $table->enum('interest_type', [
                'flat',
                'daily_balance',
                'average_daily_balance',
                'tiered',
            ])->default('daily_balance');
            $table->enum('interest_compounding_period', [
                'daily', 'weekly', 'monthly', 'quarterly', 'annually',
            ])->nullable();
            $table->enum('interest_posting_period', [
                'daily', 'monthly', 'quarterly', 'bi_annually', 'annually',
            ])->nullable();
            $table->enum('interest_calculation_days_in_year', ['360', '365'])->default('365');

            $table->decimal('min_required_opening_balance', 19, 6)->default(0);
            $table->decimal('min_required_balance', 19, 6)->default(0);

            $table->unsignedInteger('locking_period_frequency')->nullable();
            $table->enum('locking_period_frequency_type', [
                'days', 'weeks', 'months', 'years',
            ])->nullable();

            $table->unsignedInteger('min_deposit_term')->nullable();
            $table->unsignedInteger('max_deposit_term')->nullable();
            $table->enum('min_deposit_term_type', ['days', 'weeks', 'months', 'years'])->nullable();
            $table->enum('max_deposit_term_type', ['days', 'weeks', 'months', 'years'])->nullable();
            $table->unsignedInteger('in_multiples_of_deposit_term')->nullable();
            $table->enum('in_multiples_of_deposit_term_type', ['days', 'weeks', 'months', 'years'])->nullable();

            $table->decimal('min_deposit_amount', 19, 6)->nullable();
            $table->decimal('max_deposit_amount', 19, 6)->nullable();

            $table->boolean('pre_closure_penal_applicable')->default(false);
            $table->decimal('pre_closure_penal_interest', 19, 6)->nullable();
            $table->enum('pre_closure_penal_interest_on_type', ['whole_term', 'till_preclosure_date'])->nullable();

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
        Schema::dropIfExists('investment_products');
    }
};
