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
        Schema::create('investment_partial_liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('investments');
            $table->string('reference')->unique();
            $table->decimal('amount', 20, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('investment_transaction_id')->nullable()
                ->constrained('investment_transactions', 'id', 'ipl_investment_transaction_id_fk')
                ->nullOnDelete();
            $table->string('transaction_reference')->nullable();
            $table->timestamps();

            $table->index(['investment_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_partial_liquidations');
    }
};
