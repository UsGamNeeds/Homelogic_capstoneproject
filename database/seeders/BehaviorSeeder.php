<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Behavior;
use App\Models\BehaviorCategory;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;

class BehaviorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $residents = Resident::all();
        $categories = BehaviorCategory::all();
        $users = User::whereHas('roles', function($query) {
            $query->whereIn('name', ['administrator', 'super_admin', 'caregiver']);
        })->get();

        if ($residents->isEmpty() || $categories->isEmpty() || $users->isEmpty()) {
            $this->command->warn('No residents, behavior categories, or users found. Please run ResidentSeeder, BehaviorCategorySeeder, and UserSeeder first.');
            return;
        }

        $severityLevels = ['mild', 'moderate', 'severe'];
        $triggers = [
            'Change in routine',
            'Loud noises',
            'Crowded environment',
            'Medication changes',
            'Physical discomfort',
            'Emotional distress',
            'Environmental factors',
            'Social interaction',
            'Time of day',
            'Unknown'
        ];

        $interventions = [
            'Redirection and distraction',
            'Calm verbal reassurance',
            'Environmental modification',
            'Medication review',
            'Physical comfort measures',
            'Social support',
            'Activity engagement',
            'Professional consultation',
            'Family involvement',
            'Behavioral therapy'
        ];

        foreach ($residents as $resident) {
            // Create 2-6 behavior records per resident
            $behaviorCount = rand(2, 6);
            
            for ($i = 0; $i < $behaviorCount; $i++) {
                $occurredAt = Carbon::now()->subDays(rand(1, 90))->setTime(rand(6, 21), rand(0, 59));

                $category = $categories->random();
                $reportedBy = $users->random();
                $severity = $severityLevels[array_rand($severityLevels)];
                $intervention = $interventions[array_rand($interventions)];

                Behavior::create([
                    'resident_id' => $resident->id,
                    'branch_id' => $resident->branch_id,
                    'behavior_category_id' => $category->id,
                    'behavior_type' => $category->name,
                    'description' => 'Observed ' . strtolower($category->name) . ' behavior. Trigger: ' . $triggers[array_rand($triggers)],
                    'occurred_at' => $occurredAt,
                    'severity' => $severity,
                    'intervention' => $intervention,
                    'outcome' => 'Resident calmed after intervention.',
                    'reported_by' => $reportedBy->id,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            }
        }

        $this->command->info('BehaviorSeeder completed successfully!');
    }

}
