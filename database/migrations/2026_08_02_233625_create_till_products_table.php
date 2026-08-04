<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 45)->unique();
            $table->text('description')->nullable();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();

            $table->decimal('default_opening_float_amount', 19, 6)->nullable();
            $table->decimal('max_opening_float_amount', 19, 6)->nullable();

            // Variance at/under this amount auto-clears on reconcile — no second admin needed.
            $table->decimal('variance_tolerance_amount', 19, 6)->default(0);
            $table->boolean('requires_variance_approval')->default(true);

            // Off by default — most banks route excess cash through the vault, not teller-to-teller.
            $table->boolean('allows_till_to_till_transfer')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_products');
    }
};
