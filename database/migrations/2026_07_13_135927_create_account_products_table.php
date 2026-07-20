<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_products', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('code', 45)
                ->unique();

            $table->string('product_type');

            $table->text('description')
                ->nullable();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->string('currency_code', 3);

            $table->decimal('interest_rate', 19, 6)
                ->default(0);

            $table->enum('interest_type', [
                'flat',
                'daily_balance',
                'average_daily_balance',
                'tiered',
            ])->default('daily_balance');

            $table->enum('interest_compounding_period', [
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'annually',
            ])->nullable();

            $table->enum('interest_posting_period', [
                'daily',
                'monthly',
                'quarterly',
                'bi_annually',
                'annually',
            ])->nullable();

            $table->enum('interest_calculation_days_in_year', [
                '360',
                '365',
            ])->default('365');

            $table->decimal('min_required_opening_balance', 19, 6)
                ->default(0);

            $table->decimal('min_required_balance', 19, 6)
                ->default(0);

            $table->boolean('enforce_min_required_balance')
                ->default(false);

            $table->unsignedInteger('locking_period_frequency')
                ->nullable();

            $table->enum('locking_period_frequency_type', [
                'days',
                'weeks',
                'months',
                'years',
            ])->nullable();

            $table->boolean('allow_overdraft')
                ->default(false);

            $table->decimal('overdraft_limit', 19, 6)
                ->default(0);

            $table->decimal('overdraft_interest_rate', 19, 6)
                ->default(0);

            $table->boolean('withhold_tax')
                ->default(false);

            $table->boolean('is_lien_allowed')
                ->default(false);

            $table->decimal('max_allowed_lien_limit', 19, 6)
                ->default(0);

            $table->unsignedInteger('dormancy_period_days')
                ->default(365);

            $table->decimal('withdrawal_fee_amount', 19, 6)->nullable();
            $table->enum('withdrawal_fee_type', ['flat', 'percent_of_amount'])->nullable();
            $table->decimal('annual_fee_amount', 19, 6)->nullable();
            $table->unsignedTinyInteger('annual_fee_on_month')->nullable();
            $table->unsignedTinyInteger('annual_fee_on_day')->nullable();
            $table->decimal('min_balance_for_interest_calculation', 19, 6)->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index('status');
            $table->index('product_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_products');
    }
};
