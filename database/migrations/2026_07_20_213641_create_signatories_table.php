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
        Schema::create('signatories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('director_id')
                ->nullable()
                ->constrained('directors')
                ->nullOnDelete();

            $table->string('title')->nullable();

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');

            $table->string('position');

            $table->string('phone');
            $table->string('email')->nullable();

            $table->string('signature')->nullable();
            $table->string('passport_photo')->nullable();

            $table->enum('gender', [
                'male',
                'female',
            ])->nullable();

            $table->date('dob')->nullable();

            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();

            $table->text('address')->nullable();


            $table->decimal('transaction_limit', 20, 2)->nullable();

            $table->enum('status', [
                'active',
                'inactive',
                'removed',
            ])->default('active');

            $table->text('remarks')->nullable();

            $table->index('customer_id');
            $table->index('director_id');
            $table->index('phone');
            $table->index('bvn');
            $table->index('nin');
            $table->index('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatories');
    }
};
