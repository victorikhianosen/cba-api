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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->foreignId('account_product_id')
                ->constrained('account_products')
                ->restrictOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->foreignId('account_tier_id')
                ->nullable()
                ->constrained('account_tiers');

            $table->decimal('account_balance', 19, 6)
                ->default(0);

            $table->decimal('available_balance', 19, 6)
                ->default(0);
            $table->decimal('lien_balance', 19, 6)
                ->default(0);

            $table->string('account_name');

            $table->string('account_number', 20)
                ->unique();

            $table->boolean('primary_account')->default(false);

            $table->string('status')->default('active');

            $table->boolean('pnd')->default(false);
            $table->boolean('pnc')->default(false);
            $table->string('channel')->nullable();

            $table->timestamp('last_transaction_at')->nullable();


            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
