<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sleep_patterns')) {
            Schema::table('sleep_patterns', function (Blueprint $table) {
                if (!Schema::hasColumn('sleep_patterns', 'month')) {
                    $table->unsignedTinyInteger('month')->nullable()->after('resident_id');
                }
                if (!Schema::hasColumn('sleep_patterns', 'year')) {
                    $table->unsignedSmallInteger('year')->nullable()->after('month');
                }
                if (!Schema::hasColumn('sleep_patterns', 'total_awake_hours')) {
                    $table->decimal('total_awake_hours', 8, 2)->nullable()->after('total_sleep_hours');
                }
                if (!Schema::hasColumn('sleep_patterns', 'avg_sleep_hours')) {
                    $table->decimal('avg_sleep_hours', 8, 2)->nullable()->after('total_awake_hours');
                }
                if (!Schema::hasColumn('sleep_patterns', 'days_with_records')) {
                    $table->unsignedSmallInteger('days_with_records')->nullable()->after('avg_sleep_hours');
                }
                if (!Schema::hasColumn('sleep_patterns', 'common_sleep_time')) {
                    $table->string('common_sleep_time')->nullable()->after('days_with_records');
                }
                if (!Schema::hasColumn('sleep_patterns', 'common_wake_time')) {
                    $table->string('common_wake_time')->nullable()->after('common_sleep_time');
                }
                if (!Schema::hasColumn('sleep_patterns', 'sleep_quality_score')) {
                    $table->unsignedTinyInteger('sleep_quality_score')->nullable()->after('common_wake_time');
                }
                if (!Schema::hasColumn('sleep_patterns', 'key_observations')) {
                    $table->text('key_observations')->nullable()->after('sleep_quality_score');
                }
            });
        }

        if (!Schema::hasTable('sleep_hourly_data')) {
            Schema::create('sleep_hourly_data', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sleep_pattern_id')->constrained('sleep_patterns')->onDelete('cascade');
                for ($i = 0; $i < 24; $i++) {
                    $col = 'hour_' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $table->decimal($col, 4, 2)->nullable();
                }
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally not dropping columns/tables in down for safety
    }
};


