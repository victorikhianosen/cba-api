<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Restructure = heavier than Reschedule: may capitalize outstanding interest/fees
    // into principal and/or waive a portion, in addition to changing tenor/rate.
    public function up(): void
    {
        Schema::create('loan_restructures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->string('reference')->unique();

            $table->text('reason');
            $table->unsignedInteger('new_duration')->nullable();
            $table->decimal('new_interest_rate', 19, 2)->nullable();
            $table->decimal('capitalized_interest', 19, 2)->default(0);
            $table->decimal('capitalized_fees', 19, 2)->default(0);
            $table->decimal('waived_amount', 19, 2)->default(0);
            $table->decimal('restructure_fee', 19, 2)->default(0);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedInteger('old_schedule_version')->nullable();
            $table->unsignedInteger('new_schedule_version')->nullable();

            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_restructures');
    }
};
