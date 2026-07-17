<?php

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
        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();

            $table->string('gl_code', 45)->unique();
            $table->decimal('balance', 25, 6)->default(0);

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->string('currency_code')
                ->nullable();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('general_ledgers')
                ->nullOnDelete();

            $table->string('hierarchy', 100)
                ->nullable();

            $table->enum('classification', [
                'asset',
                'liability',
                'equity',
                'income',
                'expense'
            ]);

            $table->string('type');

            $table->boolean('manual_journal_entries_allowed')
                ->default(true);

            $table->string('status')->default('pending');

            $table->longText('description')
                ->nullable();

            $table->timestamps();

            $table->index('gl_code');

            $table->index('classification');

            $table->index('parent_id');

            $table->index('currency_id');

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_ledgers');
    }
};
