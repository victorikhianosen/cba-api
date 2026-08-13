<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 45)->unique();
            $table->text('description')->nullable();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('currency_code', 3);
            $table->smallInteger('currency_digits')->default(2);

            $table->decimal('interest_rate', 19, 2)->default(0);
            $table->enum('interest_rate_frequency_type', ['per_day', 'per_week', 'per_month', 'per_year'])->nullable();
            $table->enum('interest_method', ['declining_balance', 'flat'])->nullable();

            $table->unsignedBigInteger('min_principal_amount')->nullable();
            $table->unsignedBigInteger('max_principal_amount')->nullable();

            $table->unsignedInteger('repayment_every')->nullable();
            $table->enum('repayment_frequency_type', ['days', 'weeks', 'months'])->nullable();

            $table->unsignedBigInteger('arrears_tolerance_amount')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
