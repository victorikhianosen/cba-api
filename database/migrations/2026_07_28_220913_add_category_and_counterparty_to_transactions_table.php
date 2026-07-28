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
        Schema::table('transactions', function (Blueprint $table) {
            // Same free-text taxonomy already used on financial_transactions, denormalized
            // here so a single ledger row is self-describing — no join back up the chain
            // needed to know it's an interbank transfer, airtime top-up, cable subscription,
            // etc. New products/services (electricity, betting, card, whatever comes next)
            // are just new string values in these columns, never a schema or code change.
            $table->string('module')->nullable()->after('posting_type')->index(); // e.g. transfers, bills, cards, postings
            $table->string('transaction_type')->nullable()->after('module')->index(); // e.g. interbank_transfer, intrabank_transfer, airtime, data, cable_tv, electricity, betting, card_funding
            $table->string('transaction_subtype')->nullable()->after('transaction_type');
            $table->string('business_service')->nullable()->after('transaction_subtype'); // e.g. MTN Airtime, DSTV, IKEDC
            $table->string('channel')->nullable()->after('business_service')->index(); // e.g. mobile, internet_banking, ussd, pos, atm, admin, api
            $table->string('service_provider')->nullable()->after('channel'); // e.g. Interswitch, NIBSS, Baxi
            $table->string('provider_reference')->nullable()->after('service_provider')->index(); // the provider's own transaction id, for reconciliation

            // The other side of this leg, denormalized so "who sent/received this money"
            // is visible on the row itself — no join to the posting or the sibling
            // transaction leg required.
            $table->string('counterparty_type')->nullable()->after('provider_reference'); // account | general_ledger | external_bank
            $table->unsignedBigInteger('counterparty_id')->nullable()->after('counterparty_type');
            $table->string('counterparty_name')->nullable()->after('counterparty_id');
            $table->string('counterparty_account_number')->nullable()->after('counterparty_name')->index();
            $table->string('counterparty_bank_code')->nullable()->after('counterparty_account_number'); // for interbank/NIP-style transfers to other banks
            $table->string('counterparty_bank_name')->nullable()->after('counterparty_bank_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'module',
                'transaction_type',
                'transaction_subtype',
                'business_service',
                'channel',
                'service_provider',
                'provider_reference',
                'counterparty_type',
                'counterparty_id',
                'counterparty_name',
                'counterparty_account_number',
                'counterparty_bank_code',
                'counterparty_bank_name',
            ]);
        });
    }
};
