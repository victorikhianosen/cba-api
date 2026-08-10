<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            // Derived from disbursement_account_id.customer at booking, same pattern as
            // investments.customer_id — never passed directly.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();

            // Where the principal is paid OUT to on approval, and the default source
            // repayments are drawn from (a specific repayment call can name a different
            // funding account).
            $table->foreignId('disbursement_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            $table->string('account_number', 20)->unique();
            $table->decimal('principal_amount', 19, 2);

            // Snapshot of the product's terms at booking — locked so a later product edit
            // can't retroactively change an existing customer's loan. Mirrors exactly how
            // `investments` snapshots its InvestmentProduct terms.
            $table->decimal('interest_rate', 19, 2);
            $table->enum('interest_rate_frequency_type', ['per_day', 'per_week', 'per_month', 'per_year']);
            $table->enum('interest_method', ['declining_balance', 'flat']);
            $table->enum('interest_calculation_period_type', ['daily', 'same_as_repayment_period']);
            $table->enum('interest_calculation_days_in_year', ['360', '365'])->default('365');
            $table->unsignedInteger('repayment_every');
            $table->enum('repayment_frequency_type', ['days', 'weeks', 'months']);
            $table->unsignedInteger('number_of_repayments');
            $table->enum('amortization_method', ['equal_installments', 'equal_principal']);
            $table->unsignedInteger('grace_on_principal_periods')->default(0);
            $table->unsignedInteger('grace_on_interest_periods')->default(0);
            $table->unsignedInteger('grace_interest_free_periods')->default(0);
            $table->unsignedInteger('grace_on_arrears_ageing')->default(0);
            $table->decimal('arrears_tolerance_amount', 19, 2)->default(0);
            $table->decimal('penalty_interest_rate', 19, 2)->default(0);
            $table->enum('days_in_month_type', ['actual', 'thirty'])->nullable();

            // Set on approval, not booking — the term hasn't started until money has moved.
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();

            // The disbursement posting created (and immediately approved) at loan approval
            // time — approving the loan IS the authorization to release the funds, no
            // separate pending-posting state. Mirrors investments.funding_posting_id.
            $table->foreignId('disbursement_posting_id')->nullable()->constrained('postings')->nullOnDelete();

            $table->enum('status', [
                'pending', 'active', 'rejected', 'closed', 'written_off',
            ])->default('pending');

            // Running financial position — materialized, bcmath-delta-updated only, never
            // recomputed from loan_transactions history on read.
            $table->decimal('outstanding_principal', 19, 2)->nullable();
            $table->decimal('outstanding_interest', 19, 2)->default(0);
            $table->decimal('total_principal_disbursed', 19, 2)->default(0);
            $table->decimal('total_principal_paid', 19, 2)->default(0);
            $table->decimal('total_interest_paid', 19, 2)->default(0);
            $table->decimal('total_fees_charged', 19, 2)->default(0);
            $table->decimal('total_fees_paid', 19, 2)->default(0);
            $table->decimal('total_penalties_charged', 19, 2)->default(0);
            $table->decimal('total_penalties_paid', 19, 2)->default(0);
            $table->decimal('overpayment_balance', 19, 2)->default(0);
            $table->date('last_repayment_date')->nullable();
            $table->date('next_due_date')->nullable();

            // Phase 2 columns — baked in now to avoid a later ALTER even though unused
            // until the overdue-processing cron/NPL classification lands.
            $table->unsignedInteger('days_in_arrears')->default(0);
            $table->enum('npl_classification', ['performing', 'watch', 'substandard', 'doubtful', 'lost'])->default('performing');
            $table->timestamp('npl_classified_at')->nullable();
            $table->timestamp('written_off_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('npl_classification');
            $table->index('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
