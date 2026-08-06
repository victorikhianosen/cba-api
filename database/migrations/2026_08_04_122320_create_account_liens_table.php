<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_liens', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('customer_id')->constrained('customers');

            // Snapshot — kept even if the account's own number ever changes.
            $table->string('account_number')->nullable();

            $table->foreignId('currency_id')->constrained('currencies');

            $table->decimal('amount', 20, 2);
            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();

            // pending, active, released, rejected, expired — see App\Enums\Account\LienStatus.
            $table->string('status')->default('pending');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('release_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->text('release_reason')->nullable();

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_liens');
    }
};
