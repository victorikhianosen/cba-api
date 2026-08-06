<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('till_id')->constrained('tills')->restrictOnDelete();
            $table->foreignId('teller_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('opening_float_amount', 19, 2);
            // The buy-from-vault posting that funded this session's opening float.
            $table->foreignId('opening_posting_id')->nullable()->constrained('postings')->nullOnDelete();

            // Maintained incrementally alongside every till_transactions row (opening float
            // + cash_buy - cash_sell), same discipline as accounts.account_balance being
            // updated incrementally rather than recomputed from transaction history on read.
            $table->decimal('expected_closing_balance', 19, 2)->default(0);

            // Physical cash count entered by the teller at close.
            $table->decimal('counted_closing_amount', 19, 2)->nullable();
            // counted_closing_amount - expected_closing_balance, signed (negative = shortage).
            $table->decimal('variance_amount', 19, 2)->nullable();

            // The sell-to-vault posting once reconciled and the excess cash is swept back.
            $table->foreignId('closing_posting_id')->nullable()->constrained('postings')->nullOnDelete();

            $table->enum('status', [
                'open', 'pending_reconciliation', 'pending_variance_approval', 'reconciled', 'closed',
            ])->default('open');

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();

            $table->text('variance_reason')->nullable();
            $table->foreignId('variance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('variance_approved_at')->nullable();

            $table->timestamps();

            $table->index('till_id');
            $table->index(['teller_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_sessions');
    }
};
