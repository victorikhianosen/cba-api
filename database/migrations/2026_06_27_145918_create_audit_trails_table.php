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
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('performed_by_type')->nullable();    // polymorphic actor model
            $table->ulid('performed_by_id')->nullable();        // null for system/cron/job
            $table->string('performed_by_name')->nullable();

            $table->string('module')->nullable();
            $table->string('actions');
            $table->longText('description')->nullable();
            $table->json('before_change')->nullable();
            $table->json('after_change')->nullable();
            $table->string('ip')->nullable();
            $table->text('agent')->nullable();
            $table->string('channel')->nullable();
            $table->string('tenant_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
