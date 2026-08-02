<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('investment_product_id')->constrained('investment_products')->restrictOnDelete();

            // The customer's funding account — captured at creation, debited only on approval.
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();

            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            $table->string('account_number', 20)->unique();

            $table->decimal('principal_amount', 19, 6);

            // Snapshot of the product's terms at creation — locked so a later product edit
            // can't retroactively change an existing customer's deal.
            $table->decimal('interest_rate', 19, 6);
            $table->enum('interest_posting_period', [
                'upfront', 'daily', 'weekly', 'monthly', 'quarterly', 'bi_annually', 'annually', 'bullet',
            ]);
            // Always in days.
            $table->unsignedInteger('tenure');
            $table->unsignedInteger('early_liquidation_lock_days')->nullable();
            $table->decimal('early_liquidation_penalty_rate', 19, 6)->nullable();
            $table->decimal('withholding_tax_rate', 19, 2)->nullable();

            // Set on approval, not creation — the term hasn't started until money has moved.
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();

            $table->boolean('auto_renew')->default(false);
            // The customer's own choice at booking time — only collected/required when the
            // product offers auto_renew; simple rolls the original principal into a new
            // term, compound rolls principal + interest accrued so far.
            $table->enum('rollover_type', ['simple', 'compound'])->nullable();

            // The funding posting created (and immediately approved) at investment approval
            // time — approving the investment IS the authorization to move the money, so
            // there is no separate pending-posting state to wait on. See
            // App\Services\Investment\InvestmentService::approve() and
            // App\Services\Posting\PostingService::createAndApprove().
            $table->foreignId('funding_posting_id')->nullable()->constrained('postings')->nullOnDelete();

            $table->enum('status', [
                'pending', 'active', 'rejected', 'matured', 'liquidated', 'closed',
            ])->default('pending');

            // Running financial position — materialized so the current state is always available
            // without recalculating from investment_transactions history on every read.
            // Null until the funding posting is approved and the term actually starts.
            $table->decimal('current_principal', 19, 6)->nullable();
            $table->decimal('total_top_up_amount', 19, 6)->default(0);
            $table->decimal('total_withdrawal_amount', 19, 6)->default(0);
            $table->decimal('total_interest_accrued', 19, 6)->default(0);
            $table->decimal('total_interest_paid', 19, 6)->default(0);
            $table->decimal('outstanding_interest', 19, 6)->default(0);
            $table->decimal('total_fees_charged', 19, 6)->default(0);
            $table->decimal('total_penalties_charged', 19, 6)->default(0);
            $table->decimal('total_withholding_tax_deducted', 19, 6)->default(0);
            $table->decimal('net_interest_paid', 19, 6)->default(0);
            $table->decimal('maturity_value', 19, 6)->nullable();
            $table->date('last_interest_payment_date')->nullable();
            $table->date('next_interest_payment_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
