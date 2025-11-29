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
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('facility_id')->nullable()->constrained()->onDelete('set null');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('visit_purpose');
            $table->foreignId('visiting_resident_id')->nullable()->constrained('residents')->onDelete('set null');
            $table->foreignId('visiting_staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('check_in_at');
            $table->datetime('check_out_at')->nullable();
            $table->integer('expected_duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('checked_in_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for common queries
            $table->index(['branch_id', 'is_active']);
            $table->index('visiting_resident_id');
            $table->index('check_in_at');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};

