<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Top-up = additional disbursement on an existing active loan; new outstanding
    // principal = old outstanding + top-up amount, schedule regenerated from there.
    public function up(): void
    {
        Schema::create('loan_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->string('reference')->unique();

            $table->decimal('topup_amount', 19, 2);
            $table->decimal('principal_before', 19, 2)->nullable();
            $table->decimal('principal_after', 19, 2)->nullable();
            $table->unsignedInteger('new_duration')->nullable();
            $table->decimal('new_interest_rate', 19, 2)->nullable();
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('disbursement_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('loan_transaction_id')->nullable()->constrained('loan_transactions')->nullOnDelete();
            $table->unsignedInteger('old_schedule_version')->nullable();
            $table->unsignedInteger('new_schedule_version')->nullable();

            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_topups');
    }
};
