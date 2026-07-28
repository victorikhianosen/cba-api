<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions')->cascadeOnDelete();
            $table->uuid('entry_group_id')->index();
            $table->string('transaction_reference')->nullable()->index();
            $table->string('journal_reference')->nullable()->index();
            $table->foreignId('general_ledger_id')->constrained('general_ledgers');
            $table->enum('entry_type', ['debit', 'credit']);
            $table->decimal('amount', 20, 4)->default(0);
            $table->decimal('balance_before', 20, 4)->default(0);
            $table->decimal('balance_after', 20, 4)->default(0);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->string('cost_center')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('value_date')->nullable();
            $table->date('posting_date')->nullable();
            $table->text('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['general_ledger_id', 'posting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
