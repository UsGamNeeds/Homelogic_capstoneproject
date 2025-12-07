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
        Schema::create('t_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->json('types'); // Array of types: health, notes, follow-up, behavior, contacts, general
            $table->enum('notification_level', ['low', 'medium', 'high', 'urgent'])->default('low');
            $table->string('summary');
            $table->text('description')->nullable();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('reported_on')->nullable();
            $table->foreignId('entered_by_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('resident_id');
            $table->index('branch_id');
            $table->index('notification_level');
            $table->index('reported_on');
            $table->index('entered_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_logs');
    }
};
