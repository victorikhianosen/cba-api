<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directors', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('directors', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false);
            $table->index(['customer_id', 'is_primary']);
        });
    }
};
