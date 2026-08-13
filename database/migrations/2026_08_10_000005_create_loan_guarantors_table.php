<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Two distinct guarantee shapes, same table: 'personal' captures an individual's
    // identity + valid ID; 'corporate' captures a company's registration details +
    // authorizing director. Both always require a signed guarantee letter upload.
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();

            $table->enum('guarantee_type', ['personal', 'corporate']);

            // A guarantor is very often not an existing customer of the bank — captured
            // as free-form identity fields rather than forcing a customer_id, with an
            // optional link to a real Customer record when the guarantor happens to be one.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Personal guarantee fields.
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('role_in_business')->nullable();
            $table->string('id_type')->nullable(); // e.g. NIN, International Passport, Driver's License, Voter's Card
            $table->string('valid_id_path')->nullable();

            // Corporate guarantee fields.
            $table->string('company_name')->nullable();
            $table->string('rc_number')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('business_address')->nullable();
            $table->string('director_name')->nullable();
            $table->string('director_email')->nullable();

            // Required for both guarantee types.
            $table->string('signed_guarantee_letter_path')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'released'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Liability crystallizes if the borrower defaults and the guarantee is called
            // on — tracked here rather than assumed, since not every default triggers a claim.
            $table->boolean('claimed')->default(false);
            $table->decimal('claimed_amount', 19, 2)->default(0);
            $table->timestamp('claimed_at')->nullable();

            $table->timestamp('released_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};
