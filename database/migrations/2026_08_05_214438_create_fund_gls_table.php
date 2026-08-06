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
        Schema::create('fund_gls', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('transaction_reference')->index();
            $table->string('idempotency_key')->unique();
            $table->foreignId('source_id')->constrained('general_ledgers');
            $table->foreignId('destination_id')->constrained('general_ledgers');
            $table->decimal('amount', 20, 2)->default(0);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->text('remarks')->nullable();
            $table->string('status')->default('pending_review');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_id']);
            $table->index(['destination_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_gls');
    }
};
