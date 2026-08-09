<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Direct FK, not a polymorphic ProductGlMapping — a vault only ever
            // needs one GL role (its own cash balance), unlike AccountProduct/InvestmentProduct
            // which need several named roles.
            $table->foreignId('general_ledger_id')->constrained('general_ledgers')->restrictOnDelete();

            $table->string('name');
            $table->string('code', 45)->unique();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');

            $table->timestamps();

            $table->index('branch_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
