<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A single general-purpose attachment captured at booking time (any file type —
    // image, PDF, spreadsheet, ...), distinct from the structured collateral/guarantor
    // documents which each have their own dedicated path column.
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('loan_file_path')->nullable()->after('principal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('loan_file_path');
        });
    }
};
