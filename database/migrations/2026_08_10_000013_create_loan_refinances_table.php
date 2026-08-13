<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Refinance = close out an existing loan (settling its outstanding balance from a
    // brand new loan) and open a fresh loan in its place, optionally with extra cash
    // out to the customer on top of the old balance.
    public function up(): void
    {
        Schema::create('loan_refinances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('old_loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('new_loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->string('reference')->unique();

            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();
            $table->decimal('old_outstanding_amount', 19, 2)->nullable();
            $table->decimal('additional_cash_out', 19, 2)->default(0);
            $table->decimal('new_principal_amount', 19, 2)->nullable();
            $table->unsignedInteger('new_duration')->nullable();
            $table->decimal('new_interest_rate', 19, 2)->nullable();
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('disbursement_account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->timestamps();

            $table->index(['old_loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_refinances');
    }
};
