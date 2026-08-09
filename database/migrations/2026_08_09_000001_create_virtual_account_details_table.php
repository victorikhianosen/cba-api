<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_account_details', function (Blueprint $table) {
            $table->id();

            // The virtual account's own `accounts` row (product_type = 'virtual').
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();

            // Settlement account credits sweep into — immutable after creation, never
            // re-pointed (re-parenting is "close this VA, open a new one").
            $table->foreignId('parent_account_id')->constrained('accounts')->restrictOnDelete();

            $table->string('beneficiary_name'); // editable label, e.g. "Jane's Store — Till 2"
            $table->string('beneficiary_email')->nullable(); // snapshot from customer at creation
            $table->string('beneficiary_phone')->nullable();
            $table->string('beneficiary_bvn', 11)->nullable();

            $table->string('purpose')->nullable();
            $table->string('reference')->unique();
            $table->string('provider')->default('internal'); // future-proofing for a real NUBAN provider
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_account_details');
    }
};
