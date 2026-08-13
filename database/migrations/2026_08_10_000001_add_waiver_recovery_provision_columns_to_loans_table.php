<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('total_principal_waived', 19, 2)->default(0)->after('total_penalties_paid');
            $table->decimal('total_interest_waived', 19, 2)->default(0)->after('total_principal_waived');
            $table->decimal('total_fees_waived', 19, 2)->default(0)->after('total_interest_waived');
            $table->decimal('total_penalties_waived', 19, 2)->default(0)->after('total_fees_waived');

            // What was actually written off — kept even though outstanding_principal/
            // outstanding_interest are zeroed at write-off time, so "how much did we
            // actually lose on this loan" survives independent of the running balance.
            $table->decimal('written_off_principal', 19, 2)->default(0)->after('total_penalties_waived');
            $table->decimal('written_off_interest', 19, 2)->default(0)->after('written_off_principal');

            $table->decimal('total_principal_recovered', 19, 2)->default(0)->after('written_off_interest');
            $table->decimal('total_interest_recovered', 19, 2)->default(0)->after('total_principal_recovered');
            $table->decimal('total_penalty_recovered', 19, 2)->default(0)->after('total_interest_recovered');
            $table->decimal('total_fee_recovered', 19, 2)->default(0)->after('total_penalty_recovered');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'total_principal_waived', 'total_interest_waived', 'total_fees_waived', 'total_penalties_waived',
                'written_off_principal', 'written_off_interest',
                'total_principal_recovered', 'total_interest_recovered', 'total_penalty_recovered', 'total_fee_recovered',
            ]);
        });
    }
};
