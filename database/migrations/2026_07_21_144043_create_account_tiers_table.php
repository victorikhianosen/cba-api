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
        Schema::create('account_tiers', function (Blueprint $table) {
            $table->id();

            $table->tinyInteger('level')->unique();

            $table->text('description')->nullable();
            $table->string('status')->default('active');

            $table->unsignedBigInteger('daily_transaction_limit')->nullable();

            $table->unsignedBigInteger('daily_withdrawal_limit')->nullable();

            $table->unsignedBigInteger('daily_transfer_limit')->nullable();

            $table->unsignedBigInteger('max_account_balance')->nullable();

            $table->unsignedBigInteger('max_single_transaction_amount')->nullable();

            $table->unsignedBigInteger('max_daily_inflow')->nullable();

            $table->unsignedBigInteger('max_daily_outflow')->nullable();

            $table->boolean('require_bvn')->default(false);

            $table->boolean('require_nin')->default(false);

            $table->boolean('require_address_verification')->default(false);

            $table->boolean('require_face_verification')->default(false);

            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_tiers');
    }
};
