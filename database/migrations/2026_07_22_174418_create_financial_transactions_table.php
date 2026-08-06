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
         Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->string('transaction_reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->foreignId('posting_id')->nullable()->constrained('postings')->nullOnDelete();
            $table->string('module')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('transaction_subtype')->nullable();
            $table->string('business_service')->nullable();
            $table->string('channel')->nullable();
            $table->string('service_provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('amount', 20, 2)->default(0);
            $table->decimal('fee', 20, 2)->default(0);
            $table->decimal('commission', 20, 2)->default(0);
            $table->decimal('vat', 20, 2)->default(0);
            $table->decimal('discount', 20, 2)->default(0);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate', 20, 2)->default(1);
            $table->string('status')->default('pending');
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->text('narration')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('provider_reference');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
