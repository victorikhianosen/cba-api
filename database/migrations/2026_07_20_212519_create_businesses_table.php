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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->unique()
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('business_name');
            $table->string('trading_name')->nullable();

            $table->string('business_type')->nullable();

            //   'registered_business',
            //     'unregistered_business',
            //     'limited_liability',
            //     'cooperative',

            $table->string('registration_number')->nullable()->unique();
            $table->date('registration_date')->nullable();
            $table->date('incorporation_date')->nullable();

            $table->string('nature_of_business')->nullable();
            $table->string('industry')->nullable();

            $table->string('tin')->nullable()->unique();
            $table->string('vat_number')->nullable()->unique();

            $table->string('business_phone')->nullable();
            $table->string('business_email')->nullable();
            $table->string('website')->nullable();

            $table->decimal('annual_turnover', 20, 2)->nullable();
            $table->decimal('monthly_turnover', 20, 2)->nullable();

            $table->unsignedInteger('number_of_employees')->nullable();

            $table->string('source_of_funds')->nullable();

            $table->string('status')->default('pending');

            $table->index('business_name');
            $table->index('business_type');
            $table->index('registration_number');
            $table->index('tin');
            $table->index('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
