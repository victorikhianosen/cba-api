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
         Schema::create('postings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('transaction_reference')->index();
            $table->string('idempotency_key')->unique();
            $table->string('module')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('transaction_subtype')->nullable();
            $table->string('business_service')->nullable();
            $table->string('channel')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('destination_type')->nullable();
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->decimal('amount', 20, 4)->default(0);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('fee', 20, 4)->default(0);
            $table->decimal('vat', 20, 4)->default(0);
            $table->decimal('tax', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending');
            $table->unsignedInteger('approval_level')->default(0);
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->string('device_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('location')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['destination_type', 'destination_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postings');
    }
};
