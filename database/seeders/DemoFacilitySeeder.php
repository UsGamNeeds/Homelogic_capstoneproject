<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Drug;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Permission;
use App\Models\Resident;
use App\Models\Role;
use App\Models\SleepRecord;
use App\Models\TLog;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Stand-alone seeder for a self-contained demo facility used for Apple App
 * Store review. Mirrors CascadeRidgeFacilitySeeder's structure. Never touches
 * any other facility's data. Safe to re-run (firstOrCreate / updateOrCreate
 * throughout).
 *
 * Run with: php artisan db:seed --class=DemoFacilitySeeder
 */
class DemoFacilitySeeder extends Seeder
{
    private const FACILITY_NAME = 'HomeLogic360 Demo Facility';

    private const BRANCH_NAME = 'Demo Branch';

    private const ADMIN_EMAIL = 'demo.admin@homelogic360.net';

    private const CAREGIVER_EMAIL = 'demo.caregiver@homelogic360.net';

    private const DEFAULT_PASSWORD = 'DemoReview2026!';

    // Portland, OR. Used for both the facility and its branch so clock-in
    // distance checks have a single, real, consistent coordinate pair to
    // measure against.
    private const DEMO_LATITUDE = 45.51520000;

    private const DEMO_LONGITUDE = -122.67840000;

    private Facility $facility;

    private Branch $branch;

    private User $admin;

    private User $caregiver;

    public function run(): void
    {
        $this->command->info('Seeding demo facility: '.self::FACILITY_NAME);

        $this->ensureRolesAndPermissions();
        $this->createFacilityAndBranch();
        $this->assertNotRealFacility();
        $this->createUsers();
        $this->createDrugs();
        $this->createResidents();
        $this->createMedications();
        $this->createMedicationAdministrations();
        $this->createVitalSigns();
        $this->createSleepRecords();
        $this->createAppointments();
        $this->createCareNotes();

        $this->showSummary();
    }

    /**
     * Defensive guard: this seeder must never read, modify or attach anything
     * to facility_id 1 (a real facility with real patient data). If the
     * facility we resolved (whether newly created or found by name) turns
     * out to be id 1, abort loudly instead of seeding demo data into it.
     */
    private function assertNotRealFacility(): void
    {
        if ($this->facility->id === 1) {
            throw new RuntimeException(
                'DemoFacilitySeeder resolved facility_id 1, which is a real facility. '.
                'Refusing to seed demo data into it. Aborting.'
            );
        }
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

        $this->command->info('Roles ready (administrator, caregiver)');
    }

    private function createFacilityAndBranch(): void
    {
        $this->facility = Facility::withoutGlobalScopes()->firstOrCreate(
            ['name' => self::FACILITY_NAME],
            [
                'description' => 'Self-contained demo facility used for Apple App Store review.',
                'address' => '1120 NW Couch St, Portland, OR 97209',
                'phone' => '(503) 555-0100',
                'email' => 'info@homelogic360-demo.net',
                'website' => 'https://homelogic360.net',
                'license_number' => 'DEMO-REVIEW-0001',
                'license_expiry' => now()->addYear(),
                'location' => 'Portland, OR',
                'primary_color' => '#2C5F7C',
                'secondary_color' => '#8B4513',
                'accent_color' => '#F0F4F8',
                'registration_status' => 'approved',
                'is_active' => true,
                'latitude' => self::DEMO_LATITUDE,
                'longitude' => self::DEMO_LONGITUDE,
            ]
        );

        $this->branch = Branch::withoutGlobalScopes()->firstOrCreate(
            ['name' => self::BRANCH_NAME, 'facility_id' => $this->facility->id],
            [
                'address' => $this->facility->address,
                'phone' => $this->facility->phone,
                'email' => $this->facility->email,
                'is_active' => true,
                'latitude' => self::DEMO_LATITUDE,
                'longitude' => self::DEMO_LONGITUDE,
            ]
        );

        $this->command->info("Facility '{$this->facility->name}' (id {$this->facility->id}) + branch '{$this->branch->name}' ready");
    }

    private function createUsers(): void
    {
        $this->admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Demo Reviewer Admin',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'administrator',
                'facility_id' => $this->facility->id,
                'assigned_branch_id' => $this->branch->id,
                'phone_number' => '(503) 555-0101',
                'is_active' => true,
                'location_check_bypass' => true,
                'hire_date' => now()->subYear(),
                'notes' => 'Demo account for Apple App Store review. Location check bypass enabled intentionally.',
            ]
        );
        if (! $this->admin->hasRole('administrator')) {
            $this->admin->assignRole('administrator');
        }
        if (! $this->admin->location_check_bypass || ! $this->admin->is_active) {
            $this->admin->update(['location_check_bypass' => true, 'is_active' => true]);
        }

        $this->caregiver = User::firstOrCreate(
            ['email' => self::CAREGIVER_EMAIL],
            [
                'name' => 'Demo Reviewer Caregiver',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'caregiver',
                'facility_id' => $this->facility->id,
                'assigned_branch_id' => $this->branch->id,
                'phone_number' => '(503) 555-0102',
                'is_active' => true,
                'location_check_bypass' => true,
                'hire_date' => now()->subMonths(4),
                'notes' => 'Demo account for Apple App Store review. Location check bypass enabled intentionally.',
            ]
        );
        if (! $this->caregiver->hasRole('caregiver')) {
            $this->caregiver->assignRole('caregiver');
        }
        if (! $this->caregiver->location_check_bypass || ! $this->caregiver->is_active) {
            $this->caregiver->update(['location_check_bypass' => true, 'is_active' => true]);
        }

        $this->command->info('Admin + caregiver demo users ready (location_check_bypass enabled)');
    }

    private function createDrugs(): void
    {
        $drugs = [
            ['name' => 'Lisinopril', 'generic_name' => 'Lisinopril', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'indications' => 'High blood pressure', 'side_effects' => 'Dry cough, dizziness', 'is_active' => true],
            ['name' => 'Metformin', 'generic_name' => 'Metformin', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'indications' => 'Type 2 diabetes', 'side_effects' => 'Nausea, diarrhea', 'is_active' => true],
            ['name' => 'Atorvastatin', 'generic_name' => 'Atorvastatin', 'dosage_form' => 'Tablet', 'strength' => '20mg', 'indications' => 'High cholesterol', 'side_effects' => 'Muscle pain', 'is_active' => true],
        ];

        foreach ($drugs as $drugData) {
            Drug::firstOrCreate(['name' => $drugData['name'], 'strength' => $drugData['strength']], $drugData);
        }

        $this->command->info('Drugs ready');
    }

    /**
     * Three fictional residents. Names are deliberately storybook-fictional so
     * nobody mistakes them for real people, and do not appear anywhere else
     * in the codebase or other seeders.
     */
    private function createResidents(): void
    {
        $residents = [
            [
                'name' => 'Marigold Appleseed',
                'first_name' => 'Marigold',
                'last_name' => 'Appleseed',
                'date_of_birth' => '1938-06-14',
                'gender' => 'Female',
                'branch_id' => $this->branch->id,
                'room_number' => 'DEMO-101',
                'admission_date' => now()->subMonths(8),
                'emergency_contact_name' => 'Clementine Appleseed',
                'emergency_contact_phone' => '(503) 555-3001',
                'medical_conditions' => 'Hypertension, mild arthritis',
                'allergies' => 'Penicillin',
                'diagnosis' => 'Hypertension',
                'physician_name' => 'Dr. Fictional Rivera',
                'is_active' => true,
            ],
            [
                'name' => 'Bartholomew Quibble',
                'first_name' => 'Bartholomew',
                'last_name' => 'Quibble',
                'date_of_birth' => '1942-02-27',
                'gender' => 'Male',
                'branch_id' => $this->branch->id,
                'room_number' => 'DEMO-102',
                'admission_date' => now()->subMonths(3),
                'emergency_contact_name' => 'Winifred Quibble',
                'emergency_contact_phone' => '(503) 555-3002',
                'medical_conditions' => 'Type 2 diabetes, mild memory loss',
                'allergies' => 'None known',
                'diagnosis' => 'Type 2 diabetes',
                'physician_name' => 'Dr. Sample Okafor',
                'is_active' => true,
            ],
            [
                'name' => 'Petunia Winterbottom',
                'first_name' => 'Petunia',
                'last_name' => 'Winterbottom',
                'date_of_birth' => '1935-11-03',
                'gender' => 'Female',
                'branch_id' => $this->branch->id,
                'room_number' => 'DEMO-103',
                'admission_date' => now()->subMonths(1),
                'emergency_contact_name' => 'Reginald Winterbottom',
                'emergency_contact_phone' => '(503) 555-3003',
                'medical_conditions' => 'Congestive heart failure',
                'allergies' => 'Latex',
                'diagnosis' => 'Congestive heart failure',
                'physician_name' => 'Dr. Fictional Rivera',
                'is_active' => true,
            ],
        ];

        foreach ($residents as $residentData) {
            Resident::firstOrCreate(
                ['first_name' => $residentData['first_name'], 'last_name' => $residentData['last_name'], 'room_number' => $residentData['room_number']],
                $residentData
            );
        }

        $this->command->info('Residents ready ('.count($residents).')');
    }

    /**
     * One active, currently-scheduled medication per resident, with times set
     * so it shows up as due today in the app.
     */
    private function createMedications(): void
    {
        $residents = $this->demoResidents();
        $drugs = Drug::whereIn('name', ['Lisinopril', 'Metformin', 'Atorvastatin'])->get()->values();

        foreach ($residents as $i => $resident) {
            $drug = $drugs[$i % $drugs->count()];
            Medication::firstOrCreate(
                ['resident_id' => $resident->id, 'drug_id' => $drug->id, 'name' => $drug->name],
                [
                    'branch_id' => $this->branch->id,
                    'instructions' => 'Take as directed',
                    'quantity' => $drug->strength,
                    'diagnosis' => $resident->diagnosis,
                    'prescription_date' => now()->subDays(30),
                    'start_date' => now()->subDays(30),
                    'time_1' => '08:00:00',
                    'time_2' => '18:00:00',
                    'notes' => 'Take with food',
                    'created_by' => $this->admin->id,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Medications ready (due today)');
    }

    private function createMedicationAdministrations(): void
    {
        $medications = Medication::where('branch_id', $this->branch->id)->get();

        $count = 0;
        foreach ($medications as $medication) {
            for ($day = 1; $day <= 3; $day++) {
                MedicationAdministration::firstOrCreate(
                    [
                        'medication_id' => $medication->id,
                        'resident_id' => $medication->resident_id,
                        'administered_at' => now()->subDays($day)->setTime(8, 0),
                    ],
                    [
                        'branch_id' => $this->branch->id,
                        'administered_by' => $this->caregiver->id,
                        'dosage_given' => $medication->quantity,
                        'status' => 'completed',
                        'notes' => 'Administered as prescribed',
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Medication administrations ready ({$count})");
    }

    private function createVitalSigns(): void
    {
        $residents = $this->demoResidents();

        $count = 0;
        foreach ($residents as $resident) {
            for ($day = 0; $day < 3; $day++) {
                VitalSign::firstOrCreate(
                    [
                        'resident_id' => $resident->id,
                        'measurement_date' => now()->subDays($day)->toDateString(),
                    ],
                    [
                        'branch_id' => $this->branch->id,
                        'taken_by' => $this->caregiver->id,
                        'systolic' => rand(105, 145),
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

        $this->command->info("Vital signs ready ({$count})");
    }

    private function createSleepRecords(): void
    {
        $residents = $this->demoResidents();

        $count = 0;
        foreach ($residents as $resident) {
            for ($day = 0; $day < 2; $day++) {
                SleepRecord::firstOrCreate(
                    ['resident_id' => $resident->id, 'date' => now()->subDays($day)->toDateString()],
                    [
                        'branch_id' => $this->branch->id,
                        'sleep_time' => '22:00:00',
                        'wake_time' => '07:00:00',
                        'total_sleep_hours' => rand(6, 9),
                        'sleep_quality' => rand(3, 5),
                        'restlessness_episodes' => rand(0, 2),
                        'notes' => 'Regular sleep pattern',
                        'created_by' => $this->caregiver->id,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Sleep records ready ({$count})");
    }

    private function createAppointments(): void
    {
        $residents = $this->demoResidents();
        $types = ['Doctor Visit', 'Lab Work', 'Physical Therapy', 'Medication Review'];

        foreach ($residents as $i => $resident) {
            Appointment::firstOrCreate(
                ['resident_id' => $resident->id, 'title' => $types[$i % count($types)]],
                [
                    'branch_id' => $this->branch->id,
                    'appointment_date' => now()->addDays(rand(3, 30))->toDateString(),
                    'appointment_time' => '10:00:00',
                    'provider_name' => $resident->physician_name,
                    'location' => 'Portland Medical Center',
                    'description' => 'Routine demo appointment',
                    'status' => 'scheduled',
                    'created_by' => $this->admin->id,
                ]
            );
        }

        $this->command->info('Appointments ready');
    }

    private function createCareNotes(): void
    {
        $residents = $this->demoResidents();

        foreach ($residents as $resident) {
            TLog::firstOrCreate(
                ['resident_id' => $resident->id, 'summary' => 'Demo care note'],
                [
                    'branch_id' => $this->branch->id,
                    'types' => ['notes', 'general'],
                    'notification_level' => 'low',
                    'description' => 'Resident is stable and comfortable. No concerns to report today.',
                    'reporter_id' => $this->caregiver->id,
                    'entered_by_id' => $this->caregiver->id,
                    'reported_on' => now(),
                ]
            );
        }

        $this->command->info('Care notes ready');
    }

    private function demoResidents()
    {
        return Resident::where('branch_id', $this->branch->id)->get();
    }

    private function showSummary(): void
    {
        $this->command->line('');
        $this->command->line('Demo Facility Seed Summary');
        $this->command->line('  Facility: '.self::FACILITY_NAME.' (id '.$this->facility->id.')');
        $this->command->line('  Branch:   '.self::BRANCH_NAME.' (id '.$this->branch->id.')');
        $this->command->line('  Coordinates: '.self::DEMO_LATITUDE.', '.self::DEMO_LONGITUDE.' (Portland, OR)');
        $this->command->line('  Admin:     '.self::ADMIN_EMAIL.' / '.self::DEFAULT_PASSWORD);
        $this->command->line('  Caregiver: '.self::CAREGIVER_EMAIL.' / '.self::DEFAULT_PASSWORD);
        $this->command->line('  Residents: '.$this->demoResidents()->count());
        $this->command->line('  Medications: '.Medication::where('branch_id', $this->branch->id)->count());
        $this->command->line('');
        $this->command->warn('Both demo accounts have location_check_bypass enabled so App Store reviewers can clock in from anywhere.');
    }
}
