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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_type')->default('activity'); // activity, audit, error, system
            $table->string('event')->index(); // created, updated, deleted, viewed, etc.
            $table->string('subject_type')->nullable()->index(); // Model class name
            $table->unsignedBigInteger('subject_id')->nullable()->index(); // Model ID
            $table->string('description')->nullable(); // Human-readable description
            $table->json('properties')->nullable(); // Changed attributes, old/new values
            $table->json('context')->nullable(); // Additional context (IP, user agent, etc.)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('level')->default('info')->index(); // debug, info, warning, error, critical
            $table->timestamp('logged_at')->useCurrent()->index();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'logged_at']);
            $table->index(['log_type', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
