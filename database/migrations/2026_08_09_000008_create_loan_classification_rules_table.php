<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Replaces what would otherwise be a hardcoded 5-bucket NPL enum — classification
    // thresholds are regulatory/policy data, not code, so they're editable without a
    // deploy. loans.npl_classification stores a rule's `name` as a denormalized label
    // (no FK — same "editable without migrating historical rows" pattern already used
    // for financial_account_type_name on product_gl_mappings).
    public function up(): void
    {
        Schema::create('loan_classification_rules', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique(); // e.g. 'performing', 'watch' — referenced by loans.npl_classification
            $table->string('label');           // display name, e.g. "Performing"
            $table->unsignedInteger('min_days_overdue');
            $table->unsignedInteger('max_days_overdue')->nullable(); // null = unbounded (the worst/last bucket)
            $table->unsignedInteger('sort_order');
            $table->string('color')->nullable(); // hex/tailwind token for a consistent badge color in the UI
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index('sort_order');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_classification_rules');
    }
};
