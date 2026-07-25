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
        if (Schema::hasColumn('appointments', 'specialty_id')) {
            return;
        }

        // hospital_provider_id was originally added pointing at healthcare_providers
        // (reusing the Consultant catalog). Hospitals are a distinct lookup, so swap
        // it to point at the new hospitals table instead.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_provider_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('healthcare_provider_id')->constrained('hospitals')->nullOnDelete();

            // `specialty` (free text) stays for now as a fallback/legacy display value;
            // new writes go through the specialty_id lookup instead.
            $table->foreignId('specialty_id')->nullable()->after('specialty')->constrained('specialties')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_id');
            $table->dropConstrainedForeignId('specialty_id');
            $table->foreignId('hospital_provider_id')->nullable()->constrained('healthcare_providers')->nullOnDelete();
        });
    }
};
