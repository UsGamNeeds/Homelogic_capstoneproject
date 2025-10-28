<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CaregiverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();
        
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please run BranchSeeder first.');
            return;
        }

        $caregivers = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0201',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2023-01-15',
                'notes' => 'Experienced caregiver with 5 years in senior care.',
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0202',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2023-03-22',
                'notes' => 'Specializes in memory care and dementia support.',
            ],
            [
                'name' => 'Emily Rodriguez',
                'email' => 'emily.rodriguez@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0203',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2023-06-10',
                'notes' => 'Certified nursing assistant with excellent bedside manner.',
            ],
            [
                'name' => 'David Thompson',
                'email' => 'david.thompson@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0204',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2023-08-05',
                'notes' => 'Former physical therapist, great with mobility assistance.',
            ],
            [
                'name' => 'Lisa Park',
                'email' => 'lisa.park@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0205',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2023-11-12',
                'notes' => 'Bilingual caregiver, fluent in English and Korean.',
            ],
            [
                'name' => 'James Wilson',
                'email' => 'james.wilson@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0206',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2024-01-20',
                'notes' => 'Night shift specialist with gentle, patient approach.',
            ],
            [
                'name' => 'Maria Garcia',
                'email' => 'maria.garcia@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0207',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2024-03-08',
                'notes' => 'Medication management certified, very detail-oriented.',
            ],
            [
                'name' => 'Robert Brown',
                'email' => 'robert.brown@serenityafh.com',
                'password' => Hash::make('password'),
                'phone_number' => '(425) 555-0208',
                'role' => 'caregiver',
                'assigned_branch_id' => $branches->random()->id,
                'is_active' => true,
                'hire_date' => '2024-05-15',
                'notes' => 'Activity coordinator with creative engagement ideas.',
            ],
        ];

        foreach ($caregivers as $caregiverData) {
            User::firstOrCreate(
                ['email' => $caregiverData['email']],
                $caregiverData
            );
        }
    }
}