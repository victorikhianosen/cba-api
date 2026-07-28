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
      
       Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions')->cascadeOnDelete();
            $table->uuid('entry_group_id')->index();
            $table->string('transaction_reference')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('posting_type')->nullable();
            $table->enum('debit_credit', ['debit', 'credit']);
            $table->decimal('amount', 20, 4)->default(0);
            $table->decimal('balance_before', 20, 4)->default(0);
            $table->decimal('balance_after', 20, 4)->default(0);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->date('value_date')->nullable();
            $table->date('posting_date')->nullable();
            $table->string('status')->default('posted');
            $table->text('narration')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'posting_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
