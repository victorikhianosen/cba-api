<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('investments')->restrictOnDelete();

            $table->enum('type', [
                'funding', 'interest_accrual', 'interest_posting', 'top_up',
                'partial_withdrawal', 'fee', 'penalty', 'early_liquidation',
                'maturity_payout', 'renewal',
            ]);

            $table->decimal('amount', 19, 2);
            $table->decimal('principal_balance_after', 19, 2);

            $table->foreignId('posting_id')->nullable()->constrained('postings')->nullOnDelete();
            $table->foreignId('investment_schedule_id')->nullable()->constrained('investment_schedules')->nullOnDelete();

            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');
    }
};
