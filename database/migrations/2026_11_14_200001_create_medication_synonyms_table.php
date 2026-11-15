<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deterministic name-matching table for the AI progress-note medication
     * checker (plain PHP, no AI involved — see MedicationRxNormChecker).
     * Seed data is a curated starter set of common AFH medications, not a
     * full RxNorm import; expand via RxNormSync when a live feed is wired up.
     */
    public function up(): void
    {
        if (Schema::hasTable('medication_synonyms')) {
            return;
        }

        Schema::create('medication_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name');
            $table->string('rxnorm_cui')->nullable();
            $table->string('synonym');
            $table->timestamps();

            $table->unique('synonym');
            $table->index('canonical_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_synonyms');
    }
};
