<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Renames the whole Till Management schema to match Fineract's Teller/Cashier vocabulary
// and drops TillProduct entirely (see the "Rename Till Management to Teller/Cashier" plan).
// No production data exists on these tables yet, so this drops and recreates rather than
// attempting an in-place column/table rename.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('till_transactions');
        Schema::dropIfExists('till_sessions');
        Schema::dropIfExists('till_assignments');
        Schema::dropIfExists('tills');
        Schema::dropIfExists('till_products');

        Schema::create('tellers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Which vault this teller draws its float from / returns excess cash to.
            $table->foreignId('vault_id')->constrained('vaults')->restrictOnDelete();

            // Direct FK — a teller needs exactly one GL (its own cash balance), not a
            // polymorphic mapping table.
            $table->foreignId('general_ledger_id')->constrained('general_ledgers')->restrictOnDelete();

            $table->string('name');
            $table->string('code', 45)->unique();

            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();

            // Policy fields set directly at creation — no separate "product" entity.
            $table->decimal('opening_float_ceiling_amount', 19, 6)->nullable();
            $table->decimal('variance_tolerance_amount', 19, 6)->default(0);
            $table->boolean('requires_variance_approval')->default(true);
            $table->boolean('allows_teller_to_teller_transfer')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // The teller record's own lifecycle — distinct from the daily open/close
            // session state machine on cashier_sessions. A teller must be 'active' before
            // any session or cash movement is permitted against it.
            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');

            $table->timestamps();

            $table->index('branch_id');
            $table->index('vault_id');
            $table->index('status');
        });

        Schema::create('cashier_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teller_id')->constrained('tellers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');

            $table->foreignId('unassigned_by')->nullable()->constrained('users')->nullOnDelete();
            // Null = this is the teller's currently active assignment. Exactly one active
            // assignment per teller and per cashier is enforced in TellerService via a
            // locked transaction re-check, not a DB constraint (MySQL has no clean
            // filtered-unique-index).
            $table->timestamp('unassigned_at')->nullable();

            $table->timestamps();

            $table->index('teller_id');
            $table->index('user_id');
        });

        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teller_id')->constrained('tellers')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('opening_float_amount', 19, 6);
            // The buy-from-vault posting that funded this session's opening float.
            $table->foreignId('opening_posting_id')->nullable()->constrained('postings')->nullOnDelete();

            // Maintained incrementally alongside every cashier_transactions row (opening
            // float + cash_buy - cash_sell), same discipline as accounts.account_balance
            // being updated incrementally rather than recomputed from history on read.
            $table->decimal('expected_closing_balance', 19, 6)->default(0);

            // Physical cash count entered by the cashier at close.
            $table->decimal('counted_closing_amount', 19, 6)->nullable();
            // counted_closing_amount - expected_closing_balance, signed (negative = shortage).
            $table->decimal('variance_amount', 19, 6)->nullable();

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

            $table->index('teller_id');
            $table->index(['cashier_id', 'status']);
            $table->index('status');
        });

        Schema::create('cashier_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cashier_session_id')->constrained('cashier_sessions')->restrictOnDelete();
            $table->foreignId('posting_id')->nullable()->constrained('postings')->nullOnDelete();

            $table->enum('type', ['cash_buy', 'cash_sell', 'variance_adjustment']);

            // Who the teller transacted with — another cash point (vault or another teller).
            $table->enum('counterparty_type', ['vault', 'teller'])->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();

            $table->decimal('amount', 19, 6);

            // Denormalized teller balance immediately after this movement — same pattern as
            // PostingService::execute()'s counterparty snapshot, avoids recomputing history.
            $table->decimal('running_balance', 19, 6);

            $table->string('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('cashier_session_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_transactions');
        Schema::dropIfExists('cashier_sessions');
        Schema::dropIfExists('cashier_assignments');
        Schema::dropIfExists('tellers');
    }
};
