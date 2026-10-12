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
        if (Schema::hasColumn('vital_signs', 'blood_glucose')) {
            return;
        }

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->decimal('blood_glucose', 6, 1)->nullable()->after('oxygen_saturation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            if (Schema::hasColumn('vital_signs', 'blood_glucose')) {
                $table->dropColumn('blood_glucose');
            }
        });
    }
};
