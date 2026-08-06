<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Liens no longer have a configurable per-product limit — only that a lien
// can't exceed the account's own ledger balance, enforced in code.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('account_products', 'max_allowed_lien_limit')) {
            return;
        }

        Schema::table('account_products', function (Blueprint $table) {
            $table->dropColumn('max_allowed_lien_limit');
        });
    }

    public function down(): void
    {
        Schema::table('account_products', function (Blueprint $table) {
            $table->decimal('max_allowed_lien_limit', 19, 2)->default(0);
        });
    }
};
