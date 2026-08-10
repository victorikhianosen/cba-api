<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();

            $table->enum('type', [
                'disbursement',
                'repayment_principal', 'repayment_interest', 'repayment_fee', 'repayment_penalty',
                'fee_charge', 'penalty_accrual', 'penalty_charge',
                'write_off', 'restructure', 'overpayment',
                'interest_suspend', 'interest_unsuspend',
            ]);

            $table->decimal('amount', 19, 2);
            $table->decimal('outstanding_principal_after', 19, 2)->nullable();

            $table->foreignId('posting_id')->nullable()->constrained('postings')->nullOnDelete();
            $table->foreignId('loan_schedule_id')->nullable()->constrained('loan_schedules')->nullOnDelete();

            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_transactions');
    }
};
