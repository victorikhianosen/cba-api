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
        Schema::create('user_journeys', function (Blueprint $table) {
            $table->id();

            // Loose polymorphic pointer, same convention as audit_trails — no FK constraint
            // since the actor may no longer exist (or, later, could be a Customer not a User).
            $table->string('performed_by_type')->nullable();
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->string('performed_by_name')->nullable();

            // The Sanctum personal access token's id — one login (token issued) through one
            // logout/timeout (token revoked) is exactly "a session", so this is the natural
            // grouping key for reconstructing a single chronological journey.
            $table->string('session_id')->index();

            // login | logout | session_timeout | page_view | action
            $table->string('event_type')->index();

            // view | create | update | delete | search | filter | approve | reject | reverse |
            // export | download | upload | print | click | navigate | login | logout | ...
            $table->string('action')->nullable()->index();

            $table->string('module')->nullable();
            $table->string('method')->nullable();
            $table->string('route')->nullable();
            $table->text('url')->nullable();
            $table->string('description')->nullable();

            // Route params, query filters, status codes — whatever extra context helps
            // reconstruct exactly what happened without needing a fixed schema per action.
            $table->json('metadata')->nullable();

            $table->string('ip')->nullable();
            $table->text('agent')->nullable();

            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['session_id', 'occurred_at']);
            $table->index(['performed_by_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_journeys');
    }
};
