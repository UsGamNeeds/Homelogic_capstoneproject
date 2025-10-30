<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('incidents')) {
            Schema::create('incidents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resident_id')->constrained()->onDelete('cascade');
                $table->foreignId('branch_id')->constrained()->onDelete('cascade');
                $table->string('incident_type');
                $table->text('description');
                $table->dateTime('incident_date');
                $table->string('severity')->default('low');
                $table->text('action_taken')->nullable();
                $table->text('follow_up')->nullable();
                $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No-op
    }
};


