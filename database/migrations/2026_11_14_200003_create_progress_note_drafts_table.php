<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lives entirely separate from `t_logs` (the real progress-note feature).
     * A draft here is never itself a progress note — it only becomes one if a
     * human reviewer approves it, at which point an ordinary t_logs row is
     * created through the existing TLog model, untouched by this migration.
     */
    public function up(): void
    {
        if (Schema::hasTable('progress_note_drafts')) {
            return;
        }

        Schema::create('progress_note_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('facility_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->text('raw_shift_entry')->nullable();
            $table->string('window_label')->default('last 7 days');

            // { "narrative": "...", "events": [{text, source}], "medications_mentioned": [...] }
            $table->json('draft_json')->nullable();
            // { "summary": "...", "flags": [{type, detail, severity}] }
            $table->json('summary_json')->nullable();
            // Output of MedicationRxNormChecker: matched / unmatched / mismatches
            $table->json('medication_check_json')->nullable();

            $table->string('mode'); // 'anthropic' | 'heuristic'
            $table->string('model')->nullable();

            $table->enum('status', ['pending_review', 'flagged', 'approved', 'rejected'])
                ->default('pending_review');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('t_log_id')->nullable()->constrained('t_logs')->onDelete('set null');

            $table->timestamps();

            $table->index(['resident_id', 'created_at']);
            $table->index(['facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_note_drafts');
    }
};
