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
        if (Schema::hasTable('appointment_types')) {
            Schema::table('appointment_types', function (Blueprint $table) {
                if (!Schema::hasColumn('appointment_types', 'color_code')) {
                    $table->string('color_code')->nullable()->after('description');
                }
                if (!Schema::hasColumn('appointment_types', 'default_duration')) {
                    $table->integer('default_duration')->default(30)->after('color_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('appointment_types')) {
            Schema::table('appointment_types', function (Blueprint $table) {
                if (Schema::hasColumn('appointment_types', 'default_duration')) {
                    $table->dropColumn('default_duration');
                }
                if (Schema::hasColumn('appointment_types', 'color_code')) {
                    $table->dropColumn('color_code');
                }
            });
        }
    }
};


