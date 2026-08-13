<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A rough, optional estimate entered by whoever books the loan — kept separate from
    // the "no declared value" policy on the table itself (see the create migration):
    // this is explicitly the booking officer's guess, not an applicant-declared or
    // valuation-partner-assessed figure, and every place it's shown must label it as such.
    public function up(): void
    {
        Schema::table('loan_collaterals', function (Blueprint $table) {
            $table->decimal('estimated_amount', 19, 2)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('loan_collaterals', function (Blueprint $table) {
            $table->dropColumn('estimated_amount');
        });
    }
};
