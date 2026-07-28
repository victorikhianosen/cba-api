<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Plain-language reading of 'type' — credit means money came into this account
            // (inward), debit means it left (outward). Stored (not just derived on read) so
            // it can be filtered/sorted/exported directly.
            $table->string('direction')->nullable()->after('type')->index();
        });

        // Backfill existing rows from their existing 'type' value.
        DB::table('transactions')->where('type', 'credit')->update(['direction' => 'inward']);
        DB::table('transactions')->where('type', 'debit')->update(['direction' => 'outward']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
