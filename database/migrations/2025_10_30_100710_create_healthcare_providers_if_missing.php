<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('healthcare_providers')) {
            Schema::create('healthcare_providers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('specialty')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('contact_info')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // No-op to avoid dropping existing production data inadvertently
    }
};


