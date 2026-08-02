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

            // Menu of posting styles this product offers — the customer picks one when opening an investment.
            $table->json('allowed_interest_posting_periods')->nullable();

            // The band of terms this product offers — the customer picks their own tenure
            // (in days) within this range when opening an investment, same as deposit amount.
            $table->unsignedInteger('min_tenure_days')->default(30);
            $table->unsignedInteger('max_tenure_days')->default(365);

            $table->decimal('min_deposit_amount', 19, 6)->nullable();
            $table->decimal('max_deposit_amount', 19, 6)->nullable();

            // Whether auto-renewal is offered at all — the customer picks simple vs compound
            // rollover for their own investment when they open it, not here.
            $table->boolean('auto_renew')->default(false);
            $table->boolean('allow_top_up')->default(false);
            $table->boolean('allow_partial_withdrawal')->default(false);

            $table->boolean('allow_early_liquidation')->default(true);
            $table->unsignedInteger('early_liquidation_lock_days')->nullable();
            $table->decimal('early_liquidation_penalty_rate', 19, 6)->nullable();
            $table->decimal('withholding_tax_rate', 19, 2)->nullable();

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
