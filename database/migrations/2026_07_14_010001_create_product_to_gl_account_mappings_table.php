<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors Fineract's acc_product_mapping table (entity
     * ProductToGLAccountMapping) — one shared GL-mapping table for every
     * product type, discriminated by product_type + product_id instead of
     * a dedicated foreign key per product table.
     */
    public function up(): void
    {
        Schema::create('product_to_gl_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('product_type');
            $table->foreignId('general_ledger_id')->constrained('general_ledgers')->cascadeOnDelete();
            $table->smallInteger('financial_account_type');
            $table->string('financial_account_type_name');
            $table->timestamps();

            $table->index(['product_id', 'product_type']);
            $table->unique(['product_id', 'product_type', 'financial_account_type'], 'product_gl_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_to_gl_account_mappings');
    }
};
