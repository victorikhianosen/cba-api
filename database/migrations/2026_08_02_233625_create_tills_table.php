<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('till_product_id')->nullable()->constrained('till_products')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Which vault this till draws its float from / returns excess cash to.
            $table->foreignId('vault_id')->constrained('vaults')->restrictOnDelete();

            // Direct FK, same one-role reasoning as Vault — a till needs exactly one GL
            // (its own cash balance), not a polymorphic mapping table.
            $table->foreignId('general_ledger_id')->constrained('general_ledgers')->restrictOnDelete();

            $table->string('name');
            $table->string('code', 45)->unique();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();

            // Snapshot of the till product's policy at creation time — frozen so a later
            // product edit never retroactively changes an existing till's rules, same
            // discipline as investments.early_liquidation_penalty_rate being frozen off
            // investment_products.
            $table->decimal('opening_float_ceiling_amount', 19, 2)->nullable();
            $table->decimal('variance_tolerance_amount', 19, 2)->default(0);
            $table->boolean('requires_variance_approval')->default(true);
            $table->boolean('allows_till_to_till_transfer')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // The till record's own lifecycle — distinct from the daily open/close session
            // state machine on till_sessions. A till must be 'active' before any session or
            // cash movement is permitted against it.
            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');

            $table->timestamps();

            $table->index('branch_id');
            $table->index('vault_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tills');
    }
};
