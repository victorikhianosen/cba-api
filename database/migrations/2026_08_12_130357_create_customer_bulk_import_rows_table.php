<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One row per uploaded spreadsheet record — a "batch" is just every row sharing the
    // same batch_id, deliberately a single table rather than a batch-header +
    // batch-rows pair. The background job (ProcessCustomerBulkImportJob) works
    // entirely off row_data here, never re-touching the original file.
    public function up(): void
    {
        Schema::create('customer_bulk_import_rows', function (Blueprint $table) {
            $table->id();

            $table->uuid('batch_id');
            $table->string('file_name');
            $table->unsignedInteger('row_number');

            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->json('row_data');
            $table->text('error_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_bulk_import_rows');
    }
};
