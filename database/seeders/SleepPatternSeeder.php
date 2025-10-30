<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SleepPattern;
use App\Models\Resident;
use App\Models\SleepHourlyData;
use Carbon\Carbon;

class SleepPatternSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $residents = Resident::where('is_active', true)->get();
        
        if ($residents->isEmpty()) {
            $this->command->warn('No active residents found. Please run ResidentSeeder first.');
            return;
        }

        // Generate sleep patterns for the last 6 months
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = [
                'month' => $date->month,
                'year' => $date->year,
                'date' => $date
            ];
        }

        foreach ($residents as $resident) {
            foreach ($months as $monthData) {
                // Check if pattern already exists for this resident, month, year
                $existingPattern = \App\Models\SleepPattern::where('resident_id', $resident->id)
                    ->where('month', $monthData['month'])
                    ->where('year', $monthData['year'])
                    ->first();
                
                if (!$existingPattern) {
                    $sleepPattern = $this->createSleepPattern($resident, $monthData);
                    $this->createHourlyData($sleepPattern);
                }
            }
        }

        $this->command->info('Sleep patterns created successfully!');
    }

    private function createSleepPattern($resident, $monthData)
    {
        // Generate realistic sleep data
        $totalSleepHours = fake()->numberBetween(180, 240); // 6-8 hours per day average
        $totalAwakeHours = (30 * 24) - $totalSleepHours; // Assuming 30 days in month
        $avgSleepHours = $totalSleepHours / 30;
        $daysWithRecords = fake()->numberBetween(25, 30);
        
        // Common sleep and wake times
        $sleepTimes = ['21:00', '21:30', '22:00', '22:30', '23:00'];
        $wakeTimes = ['06:00', '06:30', '07:00', '07:30', '08:00'];
        
        $commonSleepTime = fake()->randomElement($sleepTimes);
        $commonWakeTime = fake()->randomElement($wakeTimes);
        
        $sleepQualityScore = fake()->numberBetween(6, 9);
        
        $observations = [
            'Consistent sleep schedule maintained',
            'Occasional restlessness noted',
            'Good sleep quality overall',
            'Some difficulty falling asleep',
            'Restful sleep patterns observed',
            'Minor sleep disruptions',
            'Excellent sleep hygiene',
            'Regular bedtime routine followed'
        ];

        return SleepPattern::create([
            'resident_id' => $resident->id,
            'branch_id' => $resident->branch_id,
            'date' => Carbon::create($monthData['year'], $monthData['month'], 1),
            'month' => $monthData['month'],
            'year' => $monthData['year'],
            'total_sleep_hours' => $totalSleepHours,
            'total_awake_hours' => $totalAwakeHours,
            'avg_sleep_hours' => round($avgSleepHours, 2),
            'days_with_records' => $daysWithRecords,
            'common_sleep_time' => $commonSleepTime,
            'common_wake_time' => $commonWakeTime,
            'sleep_quality_score' => $sleepQualityScore,
            'key_observations' => fake()->randomElement($observations),
        ]);
    }

    private function createHourlyData($sleepPattern)
    {
        $hourlyData = [];
        
        // Generate realistic hourly sleep data
        // Most sleep happens between 22:00 and 07:00
        for ($hour = 0; $hour < 24; $hour++) {
            $hourKey = 'hour_' . str_pad($hour, 2, '0', STR_PAD_LEFT);
            
            if ($hour >= 22 || $hour <= 7) {
                // Sleep hours - higher values
                $hourlyData[$hourKey] = fake()->randomFloat(2, 0.3, 1.0);
            } elseif ($hour >= 8 && $hour <= 21) {
                // Awake hours - lower values
                $hourlyData[$hourKey] = fake()->randomFloat(2, 0.0, 0.2);
            } else {
                // Transition hours
                $hourlyData[$hourKey] = fake()->randomFloat(2, 0.1, 0.5);
            }
        }

        SleepHourlyData::create(array_merge([
            'sleep_pattern_id' => $sleepPattern->id,
        ], $hourlyData));
    }
}