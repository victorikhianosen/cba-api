<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Human-readable top-up history — {"first_topup": {"amount": ..., "added_by": ...,
    // "added_at": ...}, "second_topup": {...}, ...}. This is a convenience summary on top
    // of the authoritative ledger (investment_transactions rows of type 'top_up' already
    // record every top-up with full posting/audit traceability); metadata just makes "list
    // every top-up in order, labeled" a single-column read instead of a filtered query.
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('pending_topup_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
