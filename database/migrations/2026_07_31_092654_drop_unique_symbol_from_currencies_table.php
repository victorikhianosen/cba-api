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
        Schema::table('currencies', function (Blueprint $table) {
            // Many real-world currencies share a symbol (e.g. "$" for USD, CAD, AUD,
            // ... or "£" for GBP, EGP, SHP, ...) — uniqueness here was never a valid
            // constraint and blocked seeding the full curated currency list.
            $table->dropUnique('currencies_symbol_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->unique('symbol');
        });
    }
};
