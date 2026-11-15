<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for every outbound AI call made by the progress-note agent
     * (and reusable by other AI features later). Stores redacted prompt/response
     * text only — never the raw payload — plus the identifier-scan result so a
     * leak can be investigated without re-sending anything to the vendor.
     */
    public function up(): void
    {
        if (Schema::hasTable('ai_assistant_logs')) {
            return;
        }

        Schema::create('ai_assistant_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('resident_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('feature'); // e.g. 'progress_note_draft'
            $table->string('mode');    // 'anthropic' | 'heuristic'
            $table->string('model')->nullable();
            $table->unsignedTinyInteger('tool_call_count')->default(0);
            $table->json('tool_calls')->nullable();
            $table->longText('prompt_redacted')->nullable();
            $table->longText('response_redacted')->nullable();
            $table->json('identifier_scan_hits')->nullable();
            $table->json('flags')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'created_at']);
            $table->index('feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_logs');
    }
};
