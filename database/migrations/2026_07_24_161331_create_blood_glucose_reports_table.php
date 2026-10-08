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
        // Guard against a partially-applied prior deploy re-running this migration
        // (see DEPLOYMENT.md gotcha #1).
        if (Schema::hasTable('blood_glucose_reports')) {
            return;
        }

        Schema::create('blood_glucose_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('report_date');
            $table->enum('notification_level', ['low', 'medium', 'high', 'urgent'])->default('low');

            // Blood Glucose Information
            $table->date('reading_date');
            $table->time('reading_time')->nullable();
            $table->decimal('value', 6, 1);
            $table->enum('method_used', ['fingerstick', 'cgm', 'lab_draw'])->nullable();
            $table->boolean('fasting')->nullable();
            $table->decimal('hours_since_last_meal', 4, 1)->nullable();
            $table->boolean('insulin_given')->nullable();
            $table->boolean('nurse_notified')->nullable();

            // Other Treatment
            $table->enum('treatment_type', ['none', 'insulin', 'oral_medication', 'dietary', 'other'])->nullable();
            $table->string('medication_name')->nullable();
            $table->string('medication_amount')->nullable();
            $table->string('medication_route')->nullable();
            $table->string('medication_strength')->nullable();
            $table->string('medication_frequency')->nullable();

            $table->text('comments')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blood_glucose_reports');
    }
};
