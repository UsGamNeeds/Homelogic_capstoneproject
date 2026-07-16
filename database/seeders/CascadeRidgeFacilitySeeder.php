<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Facility;
use App\Models\Branch;
use App\Models\Drug;
use App\Models\Resident;
use App\Models\Medication;
use App\Models\VitalSign;
use App\Models\VitalRange;
use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Assessment;
use App\Models\LeaveRequest;
use App\Models\SleepPattern;
use App\Models\SleepRecord;
use App\Models\MedicationAdministration;

/**
 * Stand-alone seeder for a brand new demo facility ("Cascade Ridge AFH").
 * Does not touch any other facility's data. Safe to re-run (firstOrCreate everywhere).
 *
 * Run with: php artisan db:seed --class=CascadeRidgeFacilitySeeder
 */
class CascadeRidgeFacilitySeeder extends Seeder
{
    private const FACILITY_NAME = 'Cascade Ridge Adult Family Home';
    private const BRANCH_NAME = 'Main Branch';
    private const ADMIN_EMAIL = 'admin@cascaderidgeafh.com';
    private const CAREGIVER_EMAIL = 'caregiver@cascaderidgeafh.com';
    private const DEFAULT_PASSWORD = 'password';

    private Facility $facility;
    private Branch $branch;
    private User $admin;
    private User $caregiver;

    public function run(): void
    {
        $this->command->info('🌱 Seeding new facility: ' . self::FACILITY_NAME);

        $this->ensureRolesAndPermissions();
        $this->createFacilityAndBranch();
        $this->createUsers();
        $this->createVitalRanges();
        $this->createDrugs();
        $this->createResidents();
        $this->createMedications();
        $this->createVitalSigns();
        $this->createAppointments();
        $this->createAssignments();
        $this->createAssessments();
        $this->createLeaveRequests();
        $this->createSleepData();
        $this->createMedicationAdministrations();

        $this->showSummary();
    }

    private function ensureRolesAndPermissions(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'web']);
        $caregiverRole = Role::firstOrCreate(['name' => 'caregiver', 'guard_name' => 'web']);

        // Only sync permissions if the role was just created with none assigned,
        // so we never clobber permissions an existing role already has.
        if ($adminRole->permissions()->count() === 0) {
            $adminRole->permissions()->sync(Permission::pluck('id'));
        }

        if ($caregiverRole->permissions()->count() === 0) {
            $wanted = [
                'view_admin_panel', 'view_dashboard',
                'view_own_profile', 'edit_own_profile',
                'view_residents', 'view_medications', 'view_appointments',
                'view_assessments', 'view_vital_signs', 'create_vital_signs',
                'view_assignments', 'create_leave_requests', 'view_leave_requests',
                'view_schedules', 'view_incidents', 'create_incidents',
                'view_behaviors', 'create_behaviors',
                'view_sleep_records', 'create_sleep_records', 'assign_cleaning_tasks',
            ];
            $caregiverRole->permissions()->sync(
                Permission::whereIn('name', $wanted)->pluck('id')
            );
        }

        $this->command->info('✅ Roles ready (administrator, caregiver)');
    }

    private function createFacilityAndBranch(): void
    {
        $this->facility = Facility::withoutGlobalScopes()->firstOrCreate(
            ['name' => self::FACILITY_NAME],
            [
                'description' => 'Adult Family Home providing compassionate, personalized senior care.',
                'address' => '789 Cascade Ridge Rd, Renton, WA 98058',
                'phone' => '(425) 555-0300',
                'email' => 'info@cascaderidgeafh.com',
                'website' => 'https://cascaderidgeafh.com',
                'license_number' => 'AFH-2026-003',
                'license_expiry' => now()->addYear(),
                'location' => 'Renton, WA',
                'primary_color' => '#2C5F7C',
                'secondary_color' => '#8B4513',
                'accent_color' => '#F0F4F8',
                'registration_status' => 'approved',
                'is_active' => true,
            ]
        );

        $this->branch = Branch::withoutGlobalScopes()->firstOrCreate(
            ['name' => self::BRANCH_NAME, 'facility_id' => $this->facility->id],
            [
                'address' => $this->facility->address,
                'phone' => $this->facility->phone,
                'email' => $this->facility->email,
                'is_active' => true,
            ]
        );

        $this->command->info("✅ Facility '{$this->facility->name}' + branch '{$this->branch->name}' ready");
    }

    private function createUsers(): void
    {
        $this->admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Cascade Ridge Administrator',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'administrator',
                'facility_id' => $this->facility->id,
                'assigned_branch_id' => $this->branch->id,
                'phone_number' => '(425) 555-0301',
                'is_active' => true,
                'hire_date' => now()->subYear(),
            ]
        );
        if (!$this->admin->hasRole('administrator')) {
            $this->admin->assignRole('administrator');
        }

        $this->caregiver = User::firstOrCreate(
            ['email' => self::CAREGIVER_EMAIL],
            [
                'name' => 'Jordan Casey',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'caregiver',
                'facility_id' => $this->facility->id,
                'assigned_branch_id' => $this->branch->id,
                'phone_number' => '(425) 555-0302',
                'is_active' => true,
                'hire_date' => now()->subMonths(4),
                'notes' => 'Primary caregiver for Cascade Ridge AFH.',
            ]
        );
        if (!$this->caregiver->hasRole('caregiver')) {
            $this->caregiver->assignRole('caregiver');
        }

        $this->command->info('✅ Admin + caregiver users ready');
    }

    private function createVitalRanges(): void
    {
        $ranges = [
            ['parameter' => 'systolic', 'min_normal' => 90, 'max_normal' => 120, 'min_warning' => 80, 'max_warning' => 140, 'min_critical' => 70, 'max_critical' => 180, 'unit' => 'mmHg', 'description' => 'Systolic blood pressure ranges'],
            ['parameter' => 'diastolic', 'min_normal' => 60, 'max_normal' => 80, 'min_warning' => 50, 'max_warning' => 90, 'min_critical' => 40, 'max_critical' => 110, 'unit' => 'mmHg', 'description' => 'Diastolic blood pressure ranges'],
            ['parameter' => 'temperature', 'min_normal' => 97.0, 'max_normal' => 99.0, 'min_warning' => 96.0, 'max_warning' => 100.0, 'min_critical' => 95.0, 'max_critical' => 102.0, 'unit' => '°F', 'description' => 'Body temperature ranges'],
            ['parameter' => 'pulse', 'min_normal' => 60, 'max_normal' => 100, 'min_warning' => 50, 'max_warning' => 110, 'min_critical' => 40, 'max_critical' => 120, 'unit' => 'BPM', 'description' => 'Heart rate ranges'],
            ['parameter' => 'oxygen_saturation', 'min_normal' => 95, 'max_normal' => 100, 'min_warning' => 90, 'max_warning' => 94, 'min_critical' => 85, 'max_critical' => 89, 'unit' => '%', 'description' => 'Oxygen saturation ranges'],
        ];

        foreach ($ranges as $range) {
            VitalRange::firstOrCreate(['parameter' => $range['parameter']], $range);
        }

        $this->command->info('✅ Vital ranges ready');
    }

    private function createDrugs(): void
    {
        $drugs = [
            ['name' => 'Lisinopril', 'generic_name' => 'Lisinopril', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'indications' => 'High blood pressure', 'side_effects' => 'Dry cough, dizziness', 'is_active' => true],
            ['name' => 'Metformin', 'generic_name' => 'Metformin', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'indications' => 'Type 2 diabetes', 'side_effects' => 'Nausea, diarrhea', 'is_active' => true],
            ['name' => 'Atorvastatin', 'generic_name' => 'Atorvastatin', 'dosage_form' => 'Tablet', 'strength' => '20mg', 'indications' => 'High cholesterol', 'side_effects' => 'Muscle pain', 'is_active' => true],
            ['name' => 'Donepezil', 'generic_name' => 'Donepezil', 'dosage_form' => 'Tablet', 'strength' => '5mg', 'indications' => "Alzheimer's disease", 'side_effects' => 'Nausea, vomiting', 'is_active' => true],
        ];

        foreach ($drugs as $drugData) {
            Drug::firstOrCreate(['name' => $drugData['name'], 'strength' => $drugData['strength']], $drugData);
        }

        $this->command->info('✅ Drugs ready');
    }

    private function createResidents(): void
    {
        $residents = [
            [
                'name' => 'Nancy Peterson',
                'first_name' => 'Nancy',
                'last_name' => 'Peterson',
                'date_of_birth' => '1937-04-11',
                'gender' => 'Female',
                'branch_id' => $this->branch->id,
                'room_number' => 'CR-101',
                'admission_date' => now()->subMonths(10),
                'emergency_contact_name' => 'Tom Peterson',
                'emergency_contact_phone' => '(425) 555-2001',
                'medical_conditions' => 'Hypertension, Type 2 Diabetes',
                'allergies' => 'Penicillin',
                'diagnosis' => 'Hypertension, Type 2 Diabetes',
                'physician_name' => 'Dr. Alice Nguyen',
                'is_active' => true,
            ],
            [
                'name' => 'Walter Reyes',
                'first_name' => 'Walter',
                'last_name' => 'Reyes',
                'date_of_birth' => '1941-09-02',
                'gender' => 'Male',
                'branch_id' => $this->branch->id,
                'room_number' => 'CR-102',
                'admission_date' => now()->subMonths(5),
                'emergency_contact_name' => 'Carla Reyes',
                'emergency_contact_phone' => '(425) 555-2002',
                'medical_conditions' => 'Mild Dementia, Osteoarthritis',
                'allergies' => 'None known',
                'diagnosis' => "Alzheimer's Disease, Osteoarthritis",
                'physician_name' => 'Dr. Brian Kim',
                'is_active' => true,
            ],
            [
                'name' => 'Helen Ortiz',
                'first_name' => 'Helen',
                'last_name' => 'Ortiz',
                'date_of_birth' => '1939-12-28',
                'gender' => 'Female',
                'branch_id' => $this->branch->id,
                'room_number' => 'CR-103',
                'admission_date' => now()->subMonths(2),
                'emergency_contact_name' => 'Diego Ortiz',
                'emergency_contact_phone' => '(425) 555-2003',
                'medical_conditions' => 'Congestive Heart Failure',
                'allergies' => 'Latex',
                'diagnosis' => 'Congestive Heart Failure',
                'physician_name' => 'Dr. Alice Nguyen',
                'is_active' => true,
            ],
        ];

        foreach ($residents as $residentData) {
            Resident::firstOrCreate(
                ['first_name' => $residentData['first_name'], 'last_name' => $residentData['last_name'], 'room_number' => $residentData['room_number']],
                $residentData
            );
        }

        $this->command->info('✅ Residents ready (' . count($residents) . ')');
    }

    private function createMedications(): void
    {
        $residents = Resident::where('branch_id', $this->branch->id)->get();
        $drugs = Drug::whereIn('name', ['Lisinopril', 'Metformin', 'Atorvastatin', 'Donepezil'])->get()->values();

        foreach ($residents as $i => $resident) {
            $drug = $drugs[$i % $drugs->count()];
            Medication::firstOrCreate(
                ['resident_id' => $resident->id, 'drug_id' => $drug->id, 'name' => $drug->name],
                [
                    'branch_id' => $this->branch->id,
                    'instructions' => 'Take as directed',
                    'quantity' => $drug->strength,
                    'diagnosis' => $resident->diagnosis,
                    'prescription_date' => now()->subDays(60),
                    'start_date' => now()->subDays(60),
                    'time_1' => '08:00:00',
                    'time_2' => '18:00:00',
                    'notes' => 'Take with food',
                    'created_by' => $this->admin->id,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ Medications ready');
    }

    private function createVitalSigns(): void
    {
        $residents = Resident::where('branch_id', $this->branch->id)->get();

        $count = 0;
        foreach ($residents as $resident) {
            for ($day = 0; $day < 7; $day++) {
                VitalSign::firstOrCreate(
                    [
                        'resident_id' => $resident->id,
                        'measurement_date' => now()->subDays($day)->toDateString(),
                    ],
                    [
                        'branch_id' => $this->branch->id,
                        'taken_by' => $this->caregiver->id,
                        'systolic' => rand(105, 150),
                        'diastolic' => rand(65, 88),
                        'temperature' => round(rand(970, 990) / 10, 1),
                        'pulse' => rand(62, 96),
                        'respiratory_rate' => rand(12, 20),
                        'oxygen_saturation' => rand(93, 100),
                        'status' => 'completed',
                        'notes' => 'Within normal limits',
                    ]
                );
                $count++;
            }
        }

        $this->command->info("✅ Vital signs ready ({$count})");
    }

    private function createAppointments(): void
    {
        if (Appointment::where('branch_id', $this->branch->id)->exists()) {
            $this->command->info('✅ Appointments already seeded, skipping');
            return;
        }

        $residents = Resident::where('branch_id', $this->branch->id)->get();
        $types = ['Doctor Visit', 'Lab Work', 'Physical Therapy', 'Medication Review'];

        foreach ($residents as $resident) {
            Appointment::create([
                'resident_id' => $resident->id,
                'branch_id' => $this->branch->id,
                'title' => $types[array_rand($types)],
                'description' => 'Routine appointment',
                'appointment_date' => now()->addDays(rand(3, 30)),
                'location' => 'Renton Medical Center',
                'provider_name' => 'Dr. ' . $resident->physician_name,
                'status' => 'scheduled',
                'created_by' => $this->admin->id,
            ]);
        }

        $this->command->info('✅ Appointments ready');
    }

    private function createAssignments(): void
    {
        $residents = Resident::where('branch_id', $this->branch->id)->get();

        foreach ($residents as $resident) {
            Assignment::firstOrCreate(
                ['resident_id' => $resident->id, 'caregiver_id' => $this->caregiver->id],
                [
                    'branch_id' => $this->branch->id,
                    'assigned_at' => now()->subDays(rand(1, 20)),
                    'assigned_by' => $this->admin->id,
                    'notes' => 'Primary care assignment',
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ Assignments ready');
    }

    private function createAssessments(): void
    {
        if (Assessment::where('branch_id', $this->branch->id)->exists()) {
            $this->command->info('✅ Assessments already seeded, skipping');
            return;
        }

        $residents = Resident::where('branch_id', $this->branch->id)->get();

        foreach ($residents as $resident) {
            $date = now()->subDays(rand(5, 30));
            Assessment::create([
                'resident_id' => $resident->id,
                'branch_id' => $this->branch->id,
                'assessor_id' => $this->caregiver->id,
                'assessment_type' => 'Initial Assessment',
                'assessment_date' => $date->toDateString(),
                'status' => 'completed',
                'notes' => 'Resident is stable and doing well',
                'scores' => json_encode(['overall_score' => rand(75, 95)]),
                'recommendations' => 'Continue current care plan',
                'completed_at' => $date,
            ]);
        }

        $this->command->info('✅ Assessments ready');
    }

    private function createLeaveRequests(): void
    {
        LeaveRequest::firstOrCreate(
            ['staff_id' => $this->caregiver->id, 'branch_id' => $this->branch->id, 'leave_type' => 'Vacation'],
            [
                'start_date' => now()->addDays(20)->toDateString(),
                'end_date' => now()->addDays(24)->toDateString(),
                'reason' => 'Personal time off',
                'status' => 'pending',
            ]
        );

        $this->command->info('✅ Leave request ready');
    }

    private function createSleepData(): void
    {
        $residents = Resident::where('branch_id', $this->branch->id)->get();

        foreach ($residents as $resident) {
            SleepPattern::firstOrCreate(
                ['resident_id' => $resident->id, 'date' => now()->subDay()->toDateString()],
                [
                    'branch_id' => $this->branch->id,
                    'bedtime' => '22:00:00',
                    'wake_time' => '07:00:00',
                    'total_sleep_hours' => rand(6, 9),
                    'sleep_interruptions' => rand(0, 2),
                    'notes' => 'Regular sleep pattern',
                ]
            );

            for ($day = 0; $day < 3; $day++) {
                SleepRecord::firstOrCreate(
                    ['resident_id' => $resident->id, 'date' => now()->subDays($day)->toDateString()],
                    [
                        'branch_id' => $this->branch->id,
                        'sleep_start' => '22:00:00',
                        'sleep_end' => '07:00:00',
                        'sleep_duration_minutes' => rand(360, 540),
                        'notes' => 'Good sleep quality',
                    ]
                );
            }
        }

        $this->command->info('✅ Sleep data ready');
    }

    private function createMedicationAdministrations(): void
    {
        if (MedicationAdministration::where('branch_id', $this->branch->id)->exists()) {
            $this->command->info('✅ Medication administrations already seeded, skipping');
            return;
        }

        $medications = Medication::where('branch_id', $this->branch->id)->get();

        $count = 0;
        foreach ($medications as $medication) {
            for ($day = 0; $day < 3; $day++) {
                MedicationAdministration::create([
                    'medication_id' => $medication->id,
                    'resident_id' => $medication->resident_id,
                    'branch_id' => $this->branch->id,
                    'administered_by' => $this->caregiver->id,
                    'administered_at' => now()->subDays($day)->setTime(8, 0),
                    'dosage_given' => $medication->quantity,
                    'status' => 'completed',
                    'notes' => 'Administered as prescribed',
                ]);
                $count++;
            }
        }

        $this->command->info("✅ Medication administrations ready ({$count})");
    }

    private function showSummary(): void
    {
        $this->command->line('');
        $this->command->line('📋 New Facility Seed Summary');
        $this->command->line('  🏥 Facility: ' . self::FACILITY_NAME . ' (id ' . $this->facility->id . ')');
        $this->command->line('  🏢 Branch:   ' . self::BRANCH_NAME . ' (id ' . $this->branch->id . ')');
        $this->command->line('  👤 Admin:     ' . self::ADMIN_EMAIL . ' / ' . self::DEFAULT_PASSWORD);
        $this->command->line('  🧑‍⚕️ Caregiver: ' . self::CAREGIVER_EMAIL . ' / ' . self::DEFAULT_PASSWORD);
        $this->command->line('  👴 Residents: ' . Resident::where('branch_id', $this->branch->id)->count());
        $this->command->line('  💉 Medications: ' . Medication::where('branch_id', $this->branch->id)->count());
        $this->command->line('');
        $this->command->warn('⚠️  Default password "password" — change before real use.');
    }
}
