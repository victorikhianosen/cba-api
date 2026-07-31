<?php

declare(strict_types=1);

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
            $table->string('short_name', 45)->nullable();
            $table->string('icon')->nullable();

            $table->string('code', 45)->unique();
            $table->text('description')->nullable();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('currency_code', 3);

            $table->decimal('interest_rate', 19, 6)->default(0);

            $table->enum('interest_posting_period', [
                'upfront',
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'bi_annually',
                'annually',
                'bullet',
            ])->nullable();

            $table->unsignedInteger('tenure')->default(30);
            $table->enum('tenure_unit', ['days', 'weeks', 'months', 'years'])->default('days');

            $table->decimal('min_deposit_amount', 19, 6)->nullable();
            $table->decimal('max_deposit_amount', 19, 6)->nullable();

            $table->boolean('auto_renew')->default(false);
            $table->boolean('allow_top_up')->default(false);
            $table->boolean('allow_partial_withdrawal')->default(false);

            $table->boolean('allow_early_liquidation')->default(true);
            $table->unsignedInteger('early_liquidation_lock_days')->nullable();
            $table->decimal('early_liquidation_penalty_rate', 19, 6)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->boolean('is_default')->default(false);

            $table->enum('status', ['pending', 'active', 'inactive', 'disabled', 'rejected'])->default('pending');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_products');
    }
};
