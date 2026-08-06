<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('till_session_id')->constrained('till_sessions')->restrictOnDelete();
            $table->foreignId('posting_id')->nullable()->constrained('postings')->nullOnDelete();

            $table->enum('type', ['cash_buy', 'cash_sell', 'variance_adjustment']);

            // Who the till transacted with — another cash point (vault or another till).
            $table->enum('counterparty_type', ['vault', 'till'])->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();

            $table->decimal('amount', 19, 2);

            // Denormalized till balance immediately after this movement — same pattern as
            // PostingService::execute()'s counterparty snapshot, avoids recomputing history.
            $table->decimal('running_balance', 19, 2);

            $table->string('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('till_session_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_transactions');
    }
};
