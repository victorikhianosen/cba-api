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
  
        Schema::dropIfExists('customers');

        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('cif_number')->unique();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('account_officer_id')
                ->nullable()
                ->constrained('account_officers')
                ->nullOnDelete();

            $table->string('customer_type')->default('individual');

            $table->foreignId('guardian_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->string('title')->nullable();

            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('business_name')->nullable();

            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('username')->nullable()->unique();

            $table->string('password')->nullable();
            $table->string('panic_password')->nullable();
            $table->string('pin')->nullable();

            $table->string('marital_status')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();

            $table->string('occupation')->nullable();
            $table->string('working_status')->nullable();
            $table->string('referral_code')->nullable()->unique();

            $table->string('status')->default('pending');

            $table->string('bvn')->nullable()->unique();
            $table->string('nin_number')->nullable()->unique();
            $table->string('tin')->nullable()->unique();

            $table->boolean('is_staff')->default(false);
            $table->boolean('pep')->default(false);

            $table->boolean('enable_internet_bank')->default(false);
            $table->boolean('enable_sms')->default(true);
            $table->boolean('enable_email')->default(true);
            $table->boolean('enable_reset_password')->default(false);
            $table->boolean('enable_panic_password')->default(false);

            $table->boolean('id_verified')->default(false);
            $table->boolean('face_verified')->default(false);
            $table->boolean('utility_verified')->default(false);

            $table->string('mother_maiden_name')->nullable();
            $table->string('spouse_name')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
