<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // investment_transaction_id has no FK constraint here — investment_transactions
    // references this table too (investment_transactions.investment_schedule_id), so the
    // constraint is added afterwards in add_investment_transaction_foreign_to_investment_schedules_table
    // to avoid a circular dependency between the two CREATE TABLEs.
    public function up(): void
    {
        Schema::create('investment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('investments')->cascadeOnDelete();

            $table->unsignedInteger('installment_number');
            $table->date('due_date');

            $table->decimal('opening_principal_balance', 19, 6);
            $table->decimal('expected_interest_amount', 19, 6);
            $table->decimal('closing_principal_balance', 19, 6);

            $table->decimal('paid_amount', 19, 6)->default(0);
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'partial', 'missed'])->default('pending');

            $table->unsignedBigInteger('investment_transaction_id')->nullable();

            $table->timestamps();

            $table->unique(['investment_id', 'installment_number']);
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_schedules');
    }
};
