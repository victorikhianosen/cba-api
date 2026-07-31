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
      
       Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions')->cascadeOnDelete();
            $table->uuid('entry_group_id')->index();
            $table->string('transaction_reference')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('posting_type')->nullable();

            // Same free-text taxonomy already used on financial_transactions, denormalized
            // here so a single ledger row is self-describing — no join back up the chain
            // needed to know it's an interbank transfer, airtime top-up, cable subscription,
            // etc. New products/services (electricity, betting, card, whatever comes next)
            // are just new string values in these columns, never a schema or code change.
            $table->string('module')->nullable()->index(); // e.g. transfers, bills, cards, postings
            $table->string('transaction_type')->nullable()->index(); // e.g. interbank_transfer, intrabank_transfer, airtime, data, cable_tv, electricity, betting, card_funding
            $table->string('transaction_subtype')->nullable();
            $table->string('business_service')->nullable(); // e.g. MTN Airtime, DSTV, IKEDC
            $table->string('channel')->nullable()->index(); // e.g. mobile, internet_banking, ussd, pos, atm, admin, api
            $table->string('service_provider')->nullable(); // e.g. Interswitch, NIBSS, Baxi
            $table->string('provider_reference')->nullable()->index(); // the provider's own transaction id, for reconciliation

            // The other side of this leg, denormalized so "who sent/received this money"
            // is visible on the row itself — no join to the posting or the sibling
            // transaction leg required.
            $table->string('counterparty_type')->nullable(); // account | general_ledger | external_bank
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account_number')->nullable()->index();
            $table->string('counterparty_bank_code')->nullable(); // for interbank/NIP-style transfers to other banks
            $table->string('counterparty_bank_name')->nullable();

            $table->enum('debit_credit', ['debit', 'credit']);
            $table->decimal('amount', 20, 4)->default(0);
            $table->decimal('balance_before', 20, 4)->default(0);
            $table->decimal('balance_after', 20, 4)->default(0);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->date('value_date')->nullable();
            $table->date('posting_date')->nullable();
            $table->string('status')->default('posted');
            $table->text('narration')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'posting_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
