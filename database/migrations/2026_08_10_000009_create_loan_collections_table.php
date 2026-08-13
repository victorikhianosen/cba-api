<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pure activity log — no money moves here (a promise-to-pay isn't a payment; actual
    // collection still goes through the normal LoanRepaymentService). One row per
    // collection attempt/contact, so a loan's full collections history is queryable.
    public function up(): void
    {
        Schema::create('loan_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();

            $table->foreignId('collection_officer_id')->nullable()->constrained('account_officers')->nullOnDelete();
            $table->unsignedInteger('days_past_due')->default(0); // snapshot of loan.days_in_arrears at the time of this activity

            $table->enum('contact_method', ['phone_call', 'sms', 'email', 'whatsapp', 'field_visit', 'letter', 'other']);
            $table->enum('outcome', [
                'no_answer', 'wrong_number', 'promise_to_pay', 'dispute', 'refused_to_pay',
                'paid', 'partial_payment', 'unreachable', 'other',
            ]);

            $table->date('promise_date')->nullable();
            $table->decimal('promise_amount', 19, 2)->nullable();
            $table->boolean('promise_kept')->nullable(); // null = not yet due/evaluated, true/false once the promise date passes

            $table->boolean('escalated')->default(false);
            $table->string('escalation_level')->nullable(); // e.g. 'supervisor', 'legal', 'external_agency'
            $table->boolean('legal_action_initiated')->default(false);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['loan_id', 'created_at']);
            $table->index('promise_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_collections');
    }
};
