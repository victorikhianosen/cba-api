<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Money collected on a loan AFTER it's been written off — kept as its own table
    // (rather than just loan_transactions rows) because recovery has its own metadata
    // (officer, channel, reference) a plain ledger row doesn't carry.
    public function up(): void
    {
        Schema::create('loan_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();

            $table->decimal('amount', 19, 2);
            // How the recovered amount is split across components — principal recovery is
            // real capital returned, the rest is recovered income.
            $table->decimal('principal_portion', 19, 2)->default(0);
            $table->decimal('interest_portion', 19, 2)->default(0);
            $table->decimal('penalty_portion', 19, 2)->default(0);
            $table->decimal('fee_portion', 19, 2)->default(0);

            $table->string('channel')->nullable(); // cash, transfer, collateral_realization, legal_action, ...
            $table->string('reference')->nullable();
            $table->foreignId('recovery_officer_id')->nullable()->constrained('account_officers')->nullOnDelete();
            $table->date('recovered_at');

            $table->foreignId('funding_account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_recoveries');
    }
};
