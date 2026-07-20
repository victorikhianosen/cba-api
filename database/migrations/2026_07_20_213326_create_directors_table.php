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
        Schema::create('directors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('title')->nullable();

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');

            $table->string('position');

            $table->string('phone');
            $table->string('email')->nullable();

            $table->enum('gender', [
                'male',
                'female',
            ])->nullable();

            $table->date('dob')->nullable();
            $table->string('nationality')->nullable();

            $table->text('address')->nullable();

            $table->string('occupation')->nullable();

            $table->date('appointment_date')->nullable();
            $table->date('resignation_date')->nullable();

            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();
            $table->string('tin')->nullable();

            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();

            $table->string('passport_photo')->nullable();
            $table->string('signature')->nullable();

            $table->boolean('is_primary')->default(false);

            $table->enum('status', [
                'active',
                'inactive',
                'resigned',
                'removed',
                'deceased',
            ])->default('active');

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index('customer_id');
            $table->index('phone');
            $table->index('bvn');
            $table->index('nin');
            $table->index('status');
            $table->index(['customer_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directors');
    }
};
