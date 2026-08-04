<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('till_id')->constrained('tills')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');

            $table->foreignId('unassigned_by')->nullable()->constrained('users')->nullOnDelete();
            // Null = this is the till's currently active assignment. Exactly one active
            // assignment per till and per teller is enforced in TillService via a locked
            // transaction re-check, not a DB constraint (MySQL has no clean filtered-unique-index).
            $table->timestamp('unassigned_at')->nullable();

            $table->timestamps();

            $table->index('till_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_assignments');
    }
};
