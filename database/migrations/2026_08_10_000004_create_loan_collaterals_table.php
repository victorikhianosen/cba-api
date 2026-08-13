<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Deliberately no declared value column — an internal valuation partner assesses the
    // pledged asset from the uploaded proof of ownership, the applicant never declares a
    // market value themselves.
    public function up(): void
    {
        Schema::create('loan_collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();

            $table->string('type'); // free-form: Property, Equipment, Vehicle, ...
            $table->text('description');
            $table->string('proof_of_ownership_path')->nullable();

            $table->enum('status', ['active', 'released', 'realized'])->default('active');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason')->nullable();

            // Realization = the collateral was sold/liquidated to recover an outstanding
            // balance (typically after write-off) — proceeds feed into LoanRecoveryService.
            $table->timestamp('realized_at')->nullable();
            $table->decimal('realized_amount', 19, 2)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_collaterals');
    }
};
