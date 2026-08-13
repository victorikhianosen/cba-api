<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Maker->checker->TOTP, same shape as investment_partial_liquidations /
    // virtual_account details — a request/approve/reject history table, not columns
    // bolted onto loans, since a bank needs a real audit trail of who asked for a
    // write-off and who signed off on it.
    public function up(): void
    {
        Schema::create('loan_write_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->string('reference')->unique();

            $table->decimal('principal_amount', 19, 2);
            $table->decimal('interest_amount', 19, 2);
            $table->text('reason');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('loan_transaction_id')->nullable()->constrained('loan_transactions')->nullOnDelete();

            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_write_offs');
    }
};
