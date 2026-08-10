<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // loan_transaction_id has no FK constraint here — loan_transactions references this
    // table too (loan_transactions.loan_schedule_id), so the constraint is added
    // afterwards in add_loan_transaction_foreign_to_loan_schedules_table to avoid a
    // circular dependency between the two CREATE TABLEs (mirrors investment_schedules).
    public function up(): void
    {
        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();

            $table->unsignedInteger('installment_number');
            $table->date('due_date');

            $table->decimal('opening_principal_balance', 19, 2);
            $table->decimal('principal_due', 19, 2);
            $table->decimal('interest_due', 19, 2);
            $table->decimal('closing_principal_balance', 19, 2);

            $table->decimal('principal_paid', 19, 2)->default(0);
            $table->decimal('interest_paid', 19, 2)->default(0);

            // Penalty accrues daily (Phase 2 cron) against this specific overdue
            // installment — always 1:1 with one installment, never a separate table.
            $table->decimal('penalty_due', 19, 2)->default(0);
            $table->decimal('penalty_paid', 19, 2)->default(0);

            // Ad-hoc/scheduled fees — usually 0 in Phase 1, present for completeness so a
            // mid-term fee charge has somewhere uniform to live.
            $table->decimal('fee_due', 19, 2)->default(0);
            $table->decimal('fee_paid', 19, 2)->default(0);

            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'partial', 'missed', 'superseded', 'cancelled'])->default('pending');

            $table->unsignedBigInteger('loan_transaction_id')->nullable();

            // Bumped when restructuring (Phase 2) regenerates the remaining installments —
            // old rows kept (superseded_at set), not deleted.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('accrued_at')->nullable();

            $table->timestamps();

            $table->unique(['loan_id', 'version', 'installment_number'], 'loan_schedules_id_version_installment_unique');
            $table->index('due_date');
            $table->index('status');
            $table->index('version');
            $table->index('accrued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedules');
    }
};
