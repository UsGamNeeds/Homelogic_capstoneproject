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
        if (Schema::hasTable('healthcare_providers')) {
            Schema::table('healthcare_providers', function (Blueprint $table) {
                if (!Schema::hasColumn('healthcare_providers', 'contact_info')) {
                    $table->string('contact_info')->nullable()->after('email');
                }
                if (!Schema::hasColumn('healthcare_providers', 'notes')) {
                    $table->text('notes')->nullable()->after('contact_info');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('healthcare_providers')) {
            Schema::table('healthcare_providers', function (Blueprint $table) {
                if (Schema::hasColumn('healthcare_providers', 'notes')) {
                    $table->dropColumn('notes');
                }
                if (Schema::hasColumn('healthcare_providers', 'contact_info')) {
                    $table->dropColumn('contact_info');
                }
            });
        }
    }
};


