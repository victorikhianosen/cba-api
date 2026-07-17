<?php

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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name')->unique();
            $table->string('symbol', 10)->nullable()->unique();

            $table->decimal('buy_rate', 15, 6)->nullable();
            $table->decimal('sell_rate', 15, 6)->nullable();
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->boolean('is_base_currency')->default(false);

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
