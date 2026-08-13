<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_transactions', function (Blueprint $table) {
            $table->foreignId('loan_repayment_id')->nullable()->after('loan_schedule_id')->constrained('loan_repayments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_repayment_id');
        });
    }
};
