<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('behaviors')) {
            Schema::create('behaviors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resident_id')->constrained()->onDelete('cascade');
                $table->foreignId('branch_id')->constrained()->onDelete('cascade');
                $table->foreignId('behavior_category_id')->constrained('behavior_categories')->onDelete('cascade');
                $table->string('behavior_type');
                $table->text('description');
                $table->dateTime('occurred_at');
                $table->string('severity')->default('low');
                $table->text('intervention')->nullable();
                $table->text('outcome')->nullable();
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


