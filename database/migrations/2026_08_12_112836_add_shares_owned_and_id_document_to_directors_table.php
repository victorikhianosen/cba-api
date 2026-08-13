<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Reused by every business-like legal form's "add a person" flow (directors for a
    // limited company, representatives for a cooperative, etc.) — same underlying table,
    // just relabeled per legal form on the frontend. shares_owned is the percentage of
    // the entity owned by this person; id_document is the actual uploaded ID card image,
    // distinct from id_number (the typed ID number) and passport_photo (their headshot).
    public function up(): void
    {
        Schema::table('directors', function (Blueprint $table) {
            $table->decimal('shares_owned', 5, 2)->nullable()->after('id_number');
            $table->string('id_document')->nullable()->after('shares_owned');
        });
    }

    public function down(): void
    {
        Schema::table('directors', function (Blueprint $table) {
            $table->dropColumn(['shares_owned', 'id_document']);
        });
    }
};
