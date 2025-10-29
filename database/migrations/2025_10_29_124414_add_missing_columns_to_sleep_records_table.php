<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sleep_records', function (Blueprint $table) {
            // Add sleep_date if it doesn't exist (alias for date column)
            if (!Schema::hasColumn('sleep_records', 'sleep_date')) {
                $table->date('sleep_date')->nullable()->after('branch_id');
            }
            
            // Add sleep_time if it doesn't exist (alias for sleep_start)
            if (!Schema::hasColumn('sleep_records', 'sleep_time')) {
                $table->time('sleep_time')->nullable()->after('sleep_date');
            }
            
            // Add wake_time if it doesn't exist (alias for sleep_end)
            if (!Schema::hasColumn('sleep_records', 'wake_time')) {
                $table->time('wake_time')->nullable()->after('sleep_time');
            }
            
            // Add total_sleep_hours if it doesn't exist
            if (!Schema::hasColumn('sleep_records', 'total_sleep_hours')) {
                $table->decimal('total_sleep_hours', 5, 2)->nullable()->after('wake_time');
            }
            
            // Add sleep_quality if it doesn't exist
            if (!Schema::hasColumn('sleep_records', 'sleep_quality')) {
                $table->integer('sleep_quality')->nullable()->after('total_sleep_hours');
            }
            
            // Add restlessness_episodes if it doesn't exist
            if (!Schema::hasColumn('sleep_records', 'restlessness_episodes')) {
                $table->integer('restlessness_episodes')->nullable()->default(0)->after('sleep_quality');
            }
            
            // Add created_by if it doesn't exist
            if (!Schema::hasColumn('sleep_records', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('notes');
            }
        });
        
        // Migrate existing data from old columns to new columns if they exist
        if (Schema::hasColumn('sleep_records', 'date') && Schema::hasColumn('sleep_records', 'sleep_date')) {
            DB::statement('UPDATE sleep_records SET sleep_date = date WHERE sleep_date IS NULL');
        }
        
        if (Schema::hasColumn('sleep_records', 'sleep_start') && Schema::hasColumn('sleep_records', 'sleep_time')) {
            DB::statement('UPDATE sleep_records SET sleep_time = sleep_start WHERE sleep_time IS NULL');
        }
        
        if (Schema::hasColumn('sleep_records', 'sleep_end') && Schema::hasColumn('sleep_records', 'wake_time')) {
            DB::statement('UPDATE sleep_records SET wake_time = sleep_end WHERE wake_time IS NULL');
        }
        
        if (Schema::hasColumn('sleep_records', 'sleep_duration_minutes') && Schema::hasColumn('sleep_records', 'total_sleep_hours')) {
            DB::statement('UPDATE sleep_records SET total_sleep_hours = ROUND(sleep_duration_minutes / 60.0, 2) WHERE total_sleep_hours IS NULL AND sleep_duration_minutes IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sleep_records', function (Blueprint $table) {
            if (Schema::hasColumn('sleep_records', 'sleep_date')) {
                $table->dropColumn('sleep_date');
            }
            if (Schema::hasColumn('sleep_records', 'sleep_time')) {
                $table->dropColumn('sleep_time');
            }
            if (Schema::hasColumn('sleep_records', 'wake_time')) {
                $table->dropColumn('wake_time');
            }
            if (Schema::hasColumn('sleep_records', 'total_sleep_hours')) {
                $table->dropColumn('total_sleep_hours');
            }
            if (Schema::hasColumn('sleep_records', 'sleep_quality')) {
                $table->dropColumn('sleep_quality');
            }
            if (Schema::hasColumn('sleep_records', 'restlessness_episodes')) {
                $table->dropColumn('restlessness_episodes');
            }
            if (Schema::hasColumn('sleep_records', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
