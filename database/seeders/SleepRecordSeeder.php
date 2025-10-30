<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SleepRecord;
use App\Models\Resident;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;

class SleepRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $residents = Resident::where('is_active', true)->get();
        $branches = Branch::where('is_active', true)->get();
        $users = User::where('is_active', true)->get();
        
        if ($residents->isEmpty()) {
            $this->command->warn('No active residents found. Please run ResidentSeeder first.');
            return;
        }

        if ($branches->isEmpty()) {
            $this->command->warn('No active branches found. Please run BranchSeeder first.');
            return;
        }

        if ($users->isEmpty()) {
            $this->command->warn('No active users found. Please run UserSeeder first.');
            return;
        }

        // Generate sleep records for the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            foreach ($residents as $resident) {
                // 90% chance of having a sleep record for each day
                if (fake()->boolean(90)) {
                    $this->createSleepRecord($resident, $branches->random(), $users->random(), $date);
                }
            }
        }

        $this->command->info('Sleep records created successfully!');
    }

    private function createSleepRecord($resident, $branch, $user, $date)
    {
        // Generate realistic sleep times
        $sleepTimes = [
            '21:00', '21:15', '21:30', '21:45', '22:00', '22:15', '22:30', '22:45',
            '23:00', '23:15', '23:30', '23:45', '00:00', '00:15', '00:30'
        ];
        
        $wakeTimes = [
            '06:00', '06:15', '06:30', '06:45', '07:00', '07:15', '07:30', '07:45',
            '08:00', '08:15', '08:30', '08:45', '09:00'
        ];

        $sleepTime = fake()->randomElement($sleepTimes);
        $wakeTime = fake()->randomElement($wakeTimes);
        
        // Calculate total sleep hours
        $sleepDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $sleepTime);
        $wakeDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $wakeTime);
        
        // If wake time is before sleep time, it's the next day
        if ($wakeDateTime->lessThan($sleepDateTime)) {
            $wakeDateTime->addDay();
        }
        
        $totalSleepHours = $sleepDateTime->diffInMinutes($wakeDateTime) / 60;
        
        // Ensure reasonable sleep duration (4-12 hours)
        if ($totalSleepHours < 4 || $totalSleepHours > 12) {
            $totalSleepHours = fake()->randomFloat(2, 6, 9);
        }

        $sleepQuality = fake()->numberBetween(5, 10);
        $restlessnessEpisodes = fake()->numberBetween(0, 5);
        
        $notes = [
            'Slept well through the night',
            'Restless sleep, multiple awakenings',
            'Deep sleep, no disturbances',
            'Some difficulty falling asleep',
            'Woke up refreshed',
            'Light sleep, easily awakened',
            'Good sleep quality',
            'Occasional restlessness',
            'Slept soundly',
            'Some tossing and turning',
            null, null, null // Some records without notes
        ];

        SleepRecord::create([
            'resident_id' => $resident->id,
            'branch_id' => $branch->id,
            // legacy required columns
            'date' => $date->toDateString(),
            'sleep_start' => $sleepTime,
            'sleep_end' => $wakeTime,
            'sleep_duration_minutes' => (int) round($totalSleepHours * 60),
            // new alias columns for convenience
            'sleep_date' => $date,
            'sleep_time' => $sleepTime,
            'wake_time' => $wakeTime,
            'total_sleep_hours' => round($totalSleepHours, 2),
            'sleep_quality' => $sleepQuality,
            'restlessness_episodes' => $restlessnessEpisodes,
            'notes' => fake()->randomElement($notes),
            'created_by' => $user->id,
        ]);
    }
}