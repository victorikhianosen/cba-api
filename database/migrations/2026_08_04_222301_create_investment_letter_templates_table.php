<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->string('header_image')->nullable();

            $table->string('signatory_one_name')->nullable();
            $table->string('signatory_one_signature')->nullable();

            $table->string('signatory_two_name')->nullable();
            $table->string('signatory_two_signature')->nullable();

            // Exactly one template is ever the default at a time — enforced in
            // InvestmentLetterTemplateService::setDefault(), not at the DB level.
            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_letter_templates');
    }
};
