<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ClearDashboardCache extends Command
{
    protected $signature = 'dashboard:clear-cache 
                            {--user= : Clear cache for specific user ID}
                            {--all : Clear all dashboard cache}';
    
    protected $description = 'Clear dashboard stats cache for users';

    public function handle()
    {
        $userId = $this->option('user');
        $clearAll = $this->option('all');

        if ($clearAll) {
            // Clear all dashboard cache entries
            $this->info('🗑️  Clearing all dashboard cache...');
            
            if (config('cache.default') === 'database') {
                // For database cache, delete all dashboard.stats.* keys
                $deleted = DB::table('cache')
                    ->where('key', 'like', 'dashboard.stats.%')
                    ->delete();
                
                $this->info("✅ Cleared {$deleted} dashboard cache entries.");
            } else {
                // For other cache drivers, try to flush or use cache tags if available
                $this->warn('⚠️  For non-database cache drivers, you may need to flush the entire cache.');
                $this->info('Run: php artisan cache:clear');
            }
            
            return 0;
        }

        if ($userId) {
            // Clear cache for specific user
            $user = User::find($userId);
            if (!$user) {
                $this->error("❌ User with ID {$userId} not found.");
                return 1;
            }

            $this->info("🗑️  Clearing dashboard cache for user: {$user->email} (ID: {$user->id})...");
            
            // Clear all possible cache keys for this user
            $patterns = [
                "dashboard.stats.{$user->id}.{$user->role}",
                "dashboard.stats.{$user->id}.{$user->role}.none",
            ];
            
            // Try to get facility_id and branch_id to clear specific keys
            if ($user->facility_id) {
                $patterns[] = "dashboard.stats.{$user->id}.{$user->role}.{$user->facility_id}";
            }
            
            if ($user->assigned_branch_id) {
                $branch = \App\Models\Branch::find($user->assigned_branch_id);
                if ($branch && $branch->facility_id) {
                    $patterns[] = "dashboard.stats.{$user->id}.{$user->role}.{$branch->facility_id}";
                }
            }

            $cleared = 0;
            foreach ($patterns as $pattern) {
                if (config('cache.default') === 'database') {
                    $deleted = DB::table('cache')
                        ->where('key', 'like', $pattern . '%')
                        ->delete();
                    $cleared += $deleted;
                } else {
                    Cache::forget($pattern);
                    $cleared++;
                }
            }
            
            $this->info("✅ Cleared {$cleared} cache entries for user.");
            return 0;
        }

        // No options provided, show usage
        $this->info('Dashboard Cache Clear Utility');
        $this->line('');
        $this->info('Usage:');
        $this->line('  php artisan dashboard:clear-cache --user=123  Clear cache for specific user');
        $this->line('  php artisan dashboard:clear-cache --all      Clear all dashboard cache');
        $this->line('');
        $this->info('Or visit: /api/v1/dashboard/stats?clear_cache=1 (as admin)');
        
        return 0;
    }
}
