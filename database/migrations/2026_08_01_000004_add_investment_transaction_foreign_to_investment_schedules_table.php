<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_schedules', function (Blueprint $table) {
            $table->foreign('investment_transaction_id')->references('id')->on('investment_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investment_schedules', function (Blueprint $table) {
            $table->dropForeign(['investment_transaction_id']);
        });
    }
};
