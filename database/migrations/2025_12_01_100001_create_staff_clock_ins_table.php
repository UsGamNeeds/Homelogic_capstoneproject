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
        Schema::create('staff_clock_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('facility_id')->nullable()->constrained()->onDelete('set null');
            $table->datetime('clock_in_at');
            $table->datetime('clock_out_at')->nullable();
            $table->decimal('clock_in_latitude', 10, 8)->nullable();
            $table->decimal('clock_in_longitude', 11, 8)->nullable();
            $table->decimal('clock_out_latitude', 10, 8)->nullable();
            $table->decimal('clock_out_longitude', 11, 8)->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('clock_method', ['authenticated', 'public'])->default('authenticated');
            $table->string('employee_identifier')->nullable(); // Store email/ID used for public clock-in
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for common queries
            $table->index(['staff_id', 'is_active']);
            $table->index(['branch_id', 'clock_in_at']);
            $table->index(['facility_id', 'clock_in_at']);
            $table->index('clock_method');
            $table->index('clock_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_clock_ins');
    }
};

