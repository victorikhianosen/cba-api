<?php

declare(strict_types=1);

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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category')->index();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('type')->default('string');
            $table->text('value')->nullable();
            $table->boolean('editable')->default(true);
            $table->boolean('sensitive')->default(false);
            $table->boolean('encrypted')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
