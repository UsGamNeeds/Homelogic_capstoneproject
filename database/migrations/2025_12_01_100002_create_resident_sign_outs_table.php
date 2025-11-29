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
        Schema::create('resident_sign_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('facility_id')->nullable()->constrained()->onDelete('set null');
            $table->datetime('sign_out_at');
            $table->datetime('sign_in_at')->nullable();
            $table->string('destination')->nullable();
            $table->text('purpose')->nullable();
            $table->string('accompanied_by')->nullable();
            $table->datetime('expected_return_at')->nullable();
            $table->boolean('emergency_contact_notified')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('signed_in_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for common queries
            $table->index(['resident_id', 'is_active']);
            $table->index(['branch_id', 'sign_out_at']);
            $table->index('expected_return_at'); // For overdue alerts
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_sign_outs');
    }
};

