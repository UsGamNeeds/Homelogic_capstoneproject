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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Stand-alone seeder for a self-contained demo facility used for Apple App
 * Store review and for local functional testing of the mobile app. Mirrors
 * CascadeRidgeFacilitySeeder's structure. Never touches any other facility's
 * data. Safe to re-run (firstOrCreate / updateOrCreate throughout).
 *
 * Shape: one facility, two branches, five residents per branch, and a rolling
 * seven days of medication administrations, vital signs and sleep records so
 * history screens, trends and missed-dose logic all have something to show.
 * Those seven days stop at yesterday: today is left empty on purpose so the
 * app still has doses to administer and readings to record.
 *
 * Run with: php artisan db:seed --class=DemoFacilitySeeder
 */
class DemoFacilitySeeder extends Seeder
{
    private const FACILITY_NAME = 'HomeLogic360 Demo Facility';

    /**
     * The first branch keeps its original name so an environment seeded by an
     * earlier version of this seeder gains a second branch rather than an
     * orphaned duplicate of the first.
     */
    private const BRANCH_ONE_NAME = 'Demo Branch';

    private const BRANCH_TWO_NAME = 'Demo Branch Two';

    private const ADMIN_EMAIL = 'demo.admin@homelogic360.net';

    private const CAREGIVER_ONE_EMAIL = 'demo.caregiver@homelogic360.net';

    private const CAREGIVER_TWO_EMAIL = 'demo.caregiver2@homelogic360.net';

    private const DEFAULT_PASSWORD = 'DemoReview2026!';

    /**
     * How many days of history to generate. Day 1 is yesterday: today is
     * deliberately left empty so every dose scheduled for today is still
     * actionable in the app, and so today's vitals and sleep can be recorded
     * by hand while testing. `for_administration` counts a medication as done
     * once today has a completed or refused row against it, so seeding today
     * would leave nothing to administer.
     */
    private const HISTORY_DAYS = 7;

    // Portland, OR. Used for the facility and both branches so clock-in
    // distance checks have real, consistent coordinate pairs to measure
    // against. The two branches sit about a kilometre apart: far enough to
    // tell them apart, close enough to stay plausible.
    private const DEMO_LATITUDE = 45.51520000;

    private const DEMO_LONGITUDE = -122.67840000;

    private const BRANCH_TWO_LATITUDE = 45.52310000;

    private const BRANCH_TWO_LONGITUDE = -122.67650000;

    private Facility $facility;

    /**
     * Branches keyed by the 1-based branch number used in the resident and
     * caregiver definitions below.
     *
     * @var array<int, Branch>
     */
    private array $branches = [];

    /**
     * Caregivers keyed by the branch number they staff.
     *
     * @var array<int, User>
     */
    private array $caregivers = [];

    private User $admin;

    /**
     * Resolved once after the residents are written, then reused: the set does
     * not change for the rest of the run.
     *
     * @var Collection<int, Resident>|null
     */
    private ?Collection $residents = null;

    public function run(): void
    {
        $this->command->info('Seeding demo facility: '.self::FACILITY_NAME);

        $this->ensureRolesAndPermissions();
        // The facility-id guard fires inside createFacilityAndBranches(), the
        // moment the facility is resolved and before anything is attached to
        // it. Checking here instead would be too late: the branches would
        // already be written.
        $this->createFacilityAndBranches();
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

    private function createFacilityAndBranches(): void
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

        // Before a single branch, resident or record is attached to it.
        $this->assertNotRealFacility();

        $branchDefinitions = [
            1 => [
                'name' => self::BRANCH_ONE_NAME,
                'address' => '1120 NW Couch St, Portland, OR 97209',
                'phone' => '(503) 555-0100',
                'latitude' => self::DEMO_LATITUDE,
                'longitude' => self::DEMO_LONGITUDE,
            ],
            2 => [
                'name' => self::BRANCH_TWO_NAME,
                'address' => '2250 NW Everett St, Portland, OR 97210',
                'phone' => '(503) 555-0200',
                'latitude' => self::BRANCH_TWO_LATITUDE,
                'longitude' => self::BRANCH_TWO_LONGITUDE,
            ],
        ];

        foreach ($branchDefinitions as $number => $definition) {
            $this->branches[$number] = Branch::withoutGlobalScopes()->firstOrCreate(
                ['name' => $definition['name'], 'facility_id' => $this->facility->id],
                [
                    'address' => $definition['address'],
                    'phone' => $definition['phone'],
                    'email' => $this->facility->email,
                    'is_active' => true,
                    'latitude' => $definition['latitude'],
                    'longitude' => $definition['longitude'],
                ]
            );
        }

        $branchList = collect($this->branches)
            ->map(fn (Branch $branch) => "{$branch->name} (id {$branch->id})")
            ->implode(', ');

        $this->command->info("Facility '{$this->facility->name}' (id {$this->facility->id}) ready with branches: {$branchList}");
    }

    private function createUsers(): void
    {
        // The administrator is facility-scoped so it sees both branches. It is
        // still pinned to branch 1 because some screens need a default branch.
        $this->admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Demo Reviewer Admin',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'administrator',
                'facility_id' => $this->facility->id,
                'assigned_branch_id' => $this->branches[1]->id,
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

        // One caregiver per branch. A caregiver only ever sees the branch named
        // in assigned_branch_id, so two accounts are what makes branch
        // isolation testable from the caregiver side.
        $caregiverDefinitions = [
            1 => [
                'email' => self::CAREGIVER_ONE_EMAIL,
                'name' => 'Demo Reviewer Caregiver',
                'phone_number' => '(503) 555-0102',
                'hire_date' => now()->subMonths(4),
            ],
            2 => [
                'email' => self::CAREGIVER_TWO_EMAIL,
                'name' => 'Demo Reviewer Caregiver Two',
                'phone_number' => '(503) 555-0202',
                'hire_date' => now()->subMonths(2),
            ],
        ];

        foreach ($caregiverDefinitions as $branchNumber => $definition) {
            $caregiver = User::firstOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'role' => 'caregiver',
                    'facility_id' => $this->facility->id,
                    'assigned_branch_id' => $this->branches[$branchNumber]->id,
                    'phone_number' => $definition['phone_number'],
                    'is_active' => true,
                    'location_check_bypass' => true,
                    'hire_date' => $definition['hire_date'],
                    'notes' => 'Demo account for Apple App Store review. Location check bypass enabled intentionally.',
                ]
            );

            if (! $caregiver->hasRole('caregiver')) {
                $caregiver->assignRole('caregiver');
            }

            // Re-running after the second branch was introduced must also move
            // an already-existing caregiver onto the branch it is meant to
            // staff, otherwise both would stay on branch 1.
            $wanted = [
                'location_check_bypass' => true,
                'is_active' => true,
                'assigned_branch_id' => $this->branches[$branchNumber]->id,
                'facility_id' => $this->facility->id,
            ];
            if (array_diff_assoc($wanted, $caregiver->only(array_keys($wanted)))) {
                $caregiver->update($wanted);
            }

            $this->caregivers[$branchNumber] = $caregiver;
        }

        $this->command->info('Admin + one caregiver per branch ready (location_check_bypass enabled)');
    }

    private function createDrugs(): void
    {
        $catalogue = $this->drugCatalogue();

        foreach ($catalogue as $drugData) {
            Drug::firstOrCreate(
                ['name' => $drugData['name'], 'strength' => $drugData['strength']],
                [
                    'generic_name' => $drugData['generic_name'],
                    'dosage_form' => $drugData['dosage_form'],
                    'indications' => $drugData['indications'],
                    'side_effects' => $drugData['side_effects'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Drugs ready ('.count($catalogue).')');
    }

    /**
     * Drug reference data. `times` is the daily administration schedule that
     * medications built from this drug inherit, and it drives how many
     * administration rows each resident gets per day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function drugCatalogue(): array
    {
        return [
            ['name' => 'Lisinopril', 'generic_name' => 'Lisinopril', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'indications' => 'High blood pressure', 'side_effects' => 'Dry cough, dizziness', 'times' => ['08:00:00']],
            ['name' => 'Metformin', 'generic_name' => 'Metformin', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'indications' => 'Type 2 diabetes', 'side_effects' => 'Nausea, diarrhea', 'times' => ['08:00:00', '18:00:00']],
            ['name' => 'Atorvastatin', 'generic_name' => 'Atorvastatin', 'dosage_form' => 'Tablet', 'strength' => '20mg', 'indications' => 'High cholesterol', 'side_effects' => 'Muscle pain', 'times' => ['20:00:00']],
            ['name' => 'Levothyroxine', 'generic_name' => 'Levothyroxine', 'dosage_form' => 'Tablet', 'strength' => '50mcg', 'indications' => 'Underactive thyroid', 'side_effects' => 'Palpitations if over-dosed', 'times' => ['07:00:00']],
            ['name' => 'Amlodipine', 'generic_name' => 'Amlodipine', 'dosage_form' => 'Tablet', 'strength' => '5mg', 'indications' => 'High blood pressure', 'side_effects' => 'Ankle swelling, flushing', 'times' => ['08:00:00']],
            ['name' => 'Donepezil', 'generic_name' => 'Donepezil', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'indications' => 'Alzheimer-type memory decline', 'side_effects' => 'Vivid dreams, nausea', 'times' => ['21:00:00']],
            ['name' => 'Furosemide', 'generic_name' => 'Furosemide', 'dosage_form' => 'Tablet', 'strength' => '20mg', 'indications' => 'Fluid retention in heart failure', 'side_effects' => 'Frequent urination, low potassium', 'times' => ['08:00:00', '14:00:00']],
        ];
    }

    /**
     * Ten fictional residents, five per branch. Names are deliberately
     * storybook-fictional so nobody mistakes them for real people, and do not
     * appear anywhere else in the codebase or in other seeders.
     *
     * @return array<int, array<string, mixed>>
     */
    private function residentCatalogue(): array
    {
        return [
            [
                'branch' => 1, 'first_name' => 'Marigold', 'last_name' => 'Appleseed',
                'date_of_birth' => '1938-06-14', 'gender' => 'Female', 'room_number' => 'DEMO-101',
                'admission_months_ago' => 8,
                'emergency_contact_name' => 'Clementine Appleseed', 'emergency_contact_phone' => '(503) 555-3001',
                'medical_conditions' => 'Hypertension, mild arthritis', 'allergies' => 'Penicillin',
                'diagnosis' => 'Hypertension', 'physician_name' => 'Dr. Fictional Rivera',
                'drugs' => ['Lisinopril', 'Atorvastatin'],
            ],
            [
                'branch' => 1, 'first_name' => 'Bartholomew', 'last_name' => 'Quibble',
                'date_of_birth' => '1942-02-27', 'gender' => 'Male', 'room_number' => 'DEMO-102',
                'admission_months_ago' => 3,
                'emergency_contact_name' => 'Winifred Quibble', 'emergency_contact_phone' => '(503) 555-3002',
                'medical_conditions' => 'Type 2 diabetes, mild memory loss', 'allergies' => 'None known',
                'diagnosis' => 'Type 2 diabetes', 'physician_name' => 'Dr. Sample Okafor',
                'drugs' => ['Metformin'],
            ],
            [
                'branch' => 1, 'first_name' => 'Petunia', 'last_name' => 'Winterbottom',
                'date_of_birth' => '1935-11-03', 'gender' => 'Female', 'room_number' => 'DEMO-103',
                'admission_months_ago' => 1,
                'emergency_contact_name' => 'Reginald Winterbottom', 'emergency_contact_phone' => '(503) 555-3003',
                'medical_conditions' => 'Congestive heart failure', 'allergies' => 'Latex',
                'diagnosis' => 'Congestive heart failure', 'physician_name' => 'Dr. Fictional Rivera',
                'drugs' => ['Furosemide', 'Lisinopril'],
            ],
            [
                'branch' => 1, 'first_name' => 'Cornelius', 'last_name' => 'Pemberton',
                'date_of_birth' => '1940-09-21', 'gender' => 'Male', 'room_number' => 'DEMO-104',
                'admission_months_ago' => 6,
                'emergency_contact_name' => 'Adelaide Pemberton', 'emergency_contact_phone' => '(503) 555-3004',
                'medical_conditions' => 'Underactive thyroid', 'allergies' => 'Sulfa drugs',
                'diagnosis' => 'Hypothyroidism', 'physician_name' => 'Dr. Sample Okafor',
                'drugs' => ['Levothyroxine'],
            ],
            [
                'branch' => 1, 'first_name' => 'Hyacinth', 'last_name' => 'Thistlewood',
                'date_of_birth' => '1944-04-08', 'gender' => 'Female', 'room_number' => 'DEMO-105',
                'admission_months_ago' => 2,
                'emergency_contact_name' => 'Ambrose Thistlewood', 'emergency_contact_phone' => '(503) 555-3005',
                'medical_conditions' => 'Hypertension, high cholesterol', 'allergies' => 'None known',
                'diagnosis' => 'Hypertension', 'physician_name' => 'Dr. Fictional Rivera',
                'drugs' => ['Amlodipine', 'Atorvastatin'],
            ],
            [
                'branch' => 2, 'first_name' => 'Archibald', 'last_name' => 'Fernsby',
                'date_of_birth' => '1937-12-30', 'gender' => 'Male', 'room_number' => 'DEMO-201',
                'admission_months_ago' => 10,
                'emergency_contact_name' => 'Drusilla Fernsby', 'emergency_contact_phone' => '(503) 555-3006',
                'medical_conditions' => 'Type 2 diabetes, hypertension', 'allergies' => 'Shellfish',
                'diagnosis' => 'Type 2 diabetes', 'physician_name' => 'Dr. Placeholder Nwosu',
                'drugs' => ['Metformin', 'Lisinopril'],
            ],
            [
                'branch' => 2, 'first_name' => 'Ottoline', 'last_name' => 'Marchbanks',
                'date_of_birth' => '1933-07-19', 'gender' => 'Female', 'room_number' => 'DEMO-202',
                'admission_months_ago' => 5,
                'emergency_contact_name' => 'Cuthbert Marchbanks', 'emergency_contact_phone' => '(503) 555-3007',
                'medical_conditions' => 'Alzheimer-type memory decline', 'allergies' => 'Codeine',
                'diagnosis' => 'Alzheimer disease', 'physician_name' => 'Dr. Placeholder Nwosu',
                'drugs' => ['Donepezil'],
            ],
            [
                'branch' => 2, 'first_name' => 'Percival', 'last_name' => 'Stoneworthy',
                'date_of_birth' => '1941-03-11', 'gender' => 'Male', 'room_number' => 'DEMO-203',
                'admission_months_ago' => 4,
                'emergency_contact_name' => 'Lavinia Stoneworthy', 'emergency_contact_phone' => '(503) 555-3008',
                'medical_conditions' => 'Hypertension', 'allergies' => 'None known',
                'diagnosis' => 'Hypertension', 'physician_name' => 'Dr. Example Bello',
                'drugs' => ['Amlodipine'],
            ],
            [
                'branch' => 2, 'first_name' => 'Rosalind', 'last_name' => 'Fiddlewick',
                'date_of_birth' => '1936-10-05', 'gender' => 'Female', 'room_number' => 'DEMO-204',
                'admission_months_ago' => 7,
                'emergency_contact_name' => 'Horace Fiddlewick', 'emergency_contact_phone' => '(503) 555-3009',
                'medical_conditions' => 'Congestive heart failure, high cholesterol', 'allergies' => 'Iodine',
                'diagnosis' => 'Congestive heart failure', 'physician_name' => 'Dr. Example Bello',
                'drugs' => ['Furosemide', 'Atorvastatin'],
            ],
            [
                'branch' => 2, 'first_name' => 'Mortimer', 'last_name' => 'Crumbleworth',
                'date_of_birth' => '1939-01-23', 'gender' => 'Male', 'room_number' => 'DEMO-205',
                'admission_months_ago' => 9,
                'emergency_contact_name' => 'Beatrix Crumbleworth', 'emergency_contact_phone' => '(503) 555-3010',
                'medical_conditions' => 'Underactive thyroid, type 2 diabetes', 'allergies' => 'Aspirin',
                'diagnosis' => 'Hypothyroidism', 'physician_name' => 'Dr. Placeholder Nwosu',
                'drugs' => ['Levothyroxine', 'Metformin'],
            ],
        ];
    }

    private function createResidents(): void
    {
        foreach ($this->residentCatalogue() as $definition) {
            // Matched on name plus room, deliberately not on branch: a resident
            // you move between branches by hand while testing stays where you
            // put them instead of being dragged back by the next re-run. The
            // flip side is that changing a resident's `branch` in the catalogue
            // above will not move an already-seeded row.
            Resident::withoutGlobalScopes()->firstOrCreate(
                [
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                    'room_number' => $definition['room_number'],
                ],
                [
                    'name' => $definition['first_name'].' '.$definition['last_name'],
                    'date_of_birth' => $definition['date_of_birth'],
                    'gender' => $definition['gender'],
                    'branch_id' => $this->branches[$definition['branch']]->id,
                    'admission_date' => now()->subMonths($definition['admission_months_ago']),
                    'emergency_contact_name' => $definition['emergency_contact_name'],
                    'emergency_contact_phone' => $definition['emergency_contact_phone'],
                    'medical_conditions' => $definition['medical_conditions'],
                    'allergies' => $definition['allergies'],
                    'diagnosis' => $definition['diagnosis'],
                    'physician_name' => $definition['physician_name'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info(
            'Residents ready ('.count($this->residentCatalogue()).' across '.count($this->branches).' branches)'
        );
    }

    /**
     * Each resident gets the medications named in their catalogue entry, with
     * the drug's own daily schedule written into time_1..time_4.
     */
    private function createMedications(): void
    {
        $drugsByName = Drug::whereIn('name', collect($this->drugCatalogue())->pluck('name'))
            ->get()
            ->keyBy('name');
        $scheduleByDrug = collect($this->drugCatalogue())->keyBy('name');

        $count = 0;
        foreach ($this->residentCatalogue() as $definition) {
            $resident = $this->findResident($definition);
            if (! $resident) {
                continue;
            }

            foreach ($definition['drugs'] as $drugName) {
                $drug = $drugsByName->get($drugName);
                if (! $drug) {
                    continue;
                }

                $times = $scheduleByDrug[$drugName]['times'];

                Medication::withoutGlobalScopes()->firstOrCreate(
                    ['resident_id' => $resident->id, 'drug_id' => $drug->id, 'name' => $drug->name],
                    [
                        'branch_id' => $resident->branch_id,
                        'instructions' => 'Take as directed',
                        'quantity' => $drug->strength,
                        'diagnosis' => $resident->diagnosis,
                        'prescription_date' => now()->subDays(30),
                        'start_date' => now()->subDays(30),
                        'time_1' => $times[0] ?? null,
                        'time_2' => $times[1] ?? null,
                        'time_3' => $times[2] ?? null,
                        'time_4' => $times[3] ?? null,
                        'notes' => 'Take with food',
                        'created_by' => $this->admin->id,
                        'is_active' => true,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Medications ready ({$count}, scheduled daily)");
    }

    /**
     * A rolling week of the medication administration record: one row per
     * scheduled dose per day. Statuses are mostly completed with a
     * deterministic sprinkle of missed and refused, so missed-dose counts,
     * status filters and dashboard warnings have something real to act on.
     * Doses whose scheduled time has not arrived yet are left pending.
     */
    private function createMedicationAdministrations(): void
    {
        $medications = Medication::withoutGlobalScopes()
            ->whereIn('branch_id', $this->branchIds())
            ->get();

        $counts = ['completed' => 0, 'missed' => 0, 'refused' => 0];

        foreach ($medications as $medication) {
            $caregiver = $this->caregiverForBranch($medication->branch_id);

            foreach ($this->scheduledTimes($medication) as $slot => $time) {
                for ($daysAgo = 1; $daysAgo <= self::HISTORY_DAYS; $daysAgo++) {
                    [$hour, $minute] = array_map('intval', explode(':', $time));
                    $administeredAt = now()->subDays($daysAgo)->setTime($hour, $minute);

                    $status = $this->administrationStatus($medication->id, $daysAgo, $slot);

                    MedicationAdministration::withoutGlobalScopes()->firstOrCreate(
                        [
                            'medication_id' => $medication->id,
                            'resident_id' => $medication->resident_id,
                            'administered_at' => $administeredAt,
                        ],
                        [
                            'branch_id' => $medication->branch_id,
                            // Always the staffer who recorded the dose, even when it
                            // was not given: the column is NOT NULL, and
                            // MarkMissedMedications writes a user id on missed rows too.
                            'administered_by' => $caregiver->id,
                            'dosage_given' => $status === 'completed' ? $medication->quantity : null,
                            'status' => $status,
                            'notes' => $this->administrationNote($status),
                        ]
                    );

                    $counts[$status]++;
                }
            }
        }

        $total = array_sum($counts);
        $this->command->info(
            "Medication administrations ready ({$total} over the ".self::HISTORY_DAYS.' days before today: '.
            "{$counts['completed']} completed, {$counts['missed']} missed, ".
            "{$counts['refused']} refused; today left open)"
        );
    }

    /**
     * Deterministic status for one dose, keyed on the medication, the day and
     * the time slot, so the same dose always gets the same status. Keeps
     * re-runs stable and makes anything you report reproducible.
     */
    private function administrationStatus(int $medicationId, int $daysAgo, int $slot): string
    {
        $bucket = $this->checksum("mar-{$medicationId}-{$daysAgo}-{$slot}") % 100;

        return match (true) {
            $bucket < 8 => 'missed',
            $bucket < 14 => 'refused',
            default => 'completed',
        };
    }

    private function administrationNote(string $status): string
    {
        return match ($status) {
            'completed' => 'Administered as prescribed',
            'missed' => 'Dose not given - resident was off site',
            'refused' => 'Resident declined this dose',
            default => 'Recorded by demo seeder',
        };
    }

    /**
     * The non-empty time_1..time_4 columns, in order, as HH:MM:SS strings.
     *
     * @return array<int, string>
     */
    private function scheduledTimes(Medication $medication): array
    {
        return collect([$medication->time_1, $medication->time_2, $medication->time_3, $medication->time_4])
            ->filter()
            ->map(fn ($time) => substr((string) $time, 0, 8))
            ->values()
            ->all();
    }

    private function createVitalSigns(): void
    {
        $count = 0;
        foreach ($this->demoResidents() as $resident) {
            $caregiver = $this->caregiverForBranch($resident->branch_id);

            for ($daysAgo = 1; $daysAgo <= self::HISTORY_DAYS; $daysAgo++) {
                $seed = "vitals-{$resident->id}-{$daysAgo}";

                VitalSign::withoutGlobalScopes()->firstOrCreate(
                    [
                        'resident_id' => $resident->id,
                        // A Carbon instance, not a Y-m-d string. VitalSign casts
                        // measurement_date to 'date', which stores it as
                        // "Y-m-d 00:00:00"; matching on a bare date string never
                        // finds that row, so every re-run used to insert a
                        // duplicate set of vitals.
                        'measurement_date' => now()->subDays($daysAgo)->startOfDay(),
                    ],
                    [
                        'branch_id' => $resident->branch_id,
                        'taken_by' => $caregiver->id,
                        'systolic' => $this->spread($seed.'-systolic', 105, 145),
                        'diastolic' => $this->spread($seed.'-diastolic', 65, 88),
                        'temperature' => round($this->spread($seed.'-temperature', 970, 990) / 10, 1),
                        'pulse' => $this->spread($seed.'-pulse', 62, 96),
                        'respiratory_rate' => $this->spread($seed.'-respiratory', 12, 20),
                        'oxygen_saturation' => $this->spread($seed.'-oxygen', 93, 100),
                        'status' => 'completed',
                        'notes' => 'Within normal limits',
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Vital signs ready ({$count} over the ".self::HISTORY_DAYS.' days before today)');
    }

    private function createSleepRecords(): void
    {
        $count = 0;
        foreach ($this->demoResidents() as $resident) {
            $caregiver = $this->caregiverForBranch($resident->branch_id);

            for ($daysAgo = 1; $daysAgo <= self::HISTORY_DAYS; $daysAgo++) {
                $seed = "sleep-{$resident->id}-{$daysAgo}";

                SleepRecord::withoutGlobalScopes()->firstOrCreate(
                    ['resident_id' => $resident->id, 'date' => now()->subDays($daysAgo)->toDateString()],
                    [
                        'branch_id' => $resident->branch_id,
                        'sleep_time' => '22:00:00',
                        'wake_time' => '07:00:00',
                        'total_sleep_hours' => $this->spread($seed.'-hours', 6, 9),
                        'sleep_quality' => $this->spread($seed.'-quality', 3, 5),
                        'restlessness_episodes' => $this->spread($seed.'-restless', 0, 2),
                        'notes' => 'Regular sleep pattern',
                        'created_by' => $caregiver->id,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Sleep records ready ({$count} over the ".self::HISTORY_DAYS.' days before today)');
    }

    private function createAppointments(): void
    {
        $types = ['Doctor Visit', 'Lab Work', 'Physical Therapy', 'Medication Review'];

        $count = 0;
        foreach ($this->demoResidents()->values() as $i => $resident) {
            Appointment::withoutGlobalScopes()->firstOrCreate(
                ['resident_id' => $resident->id, 'title' => $types[$i % count($types)]],
                [
                    'branch_id' => $resident->branch_id,
                    'appointment_date' => now()->addDays($this->spread("appt-{$resident->id}", 3, 30))->toDateString(),
                    'appointment_time' => '10:00:00',
                    'provider_name' => $resident->physician_name,
                    'location' => 'Portland Medical Center',
                    'description' => 'Routine demo appointment',
                    'status' => 'scheduled',
                    'created_by' => $this->admin->id,
                ]
            );
            $count++;
        }

        $this->command->info("Appointments ready ({$count})");
    }

    private function createCareNotes(): void
    {
        $count = 0;
        foreach ($this->demoResidents() as $resident) {
            $caregiver = $this->caregiverForBranch($resident->branch_id);

            TLog::withoutGlobalScopes()->firstOrCreate(
                ['resident_id' => $resident->id, 'summary' => 'Demo care note'],
                [
                    'branch_id' => $resident->branch_id,
                    'types' => ['notes', 'general'],
                    'notification_level' => 'low',
                    'description' => 'Resident is stable and comfortable. No concerns to report today.',
                    'reporter_id' => $caregiver->id,
                    'entered_by_id' => $caregiver->id,
                    'reported_on' => now(),
                ]
            );
            $count++;
        }

        $this->command->info("Care notes ready ({$count})");
    }

    /**
     * A stable value in [$min, $max] derived from a seed string, so re-running
     * the seeder never produces a different number for the same record.
     */
    private function spread(string $seed, int $min, int $max): int
    {
        return $min + (int) ($this->checksum($seed) % (($max - $min) + 1));
    }

    /**
     * crc32() returns a signed int, which is negative on 32-bit builds once the
     * unsigned checksum overflows. PHP's modulo takes the sign of the dividend,
     * so a raw crc32() could push spread() below its minimum and skew the
     * status thresholds. Read it back as unsigned to keep the arithmetic sane
     * on any platform.
     */
    private function checksum(string $seed): int
    {
        return (int) sprintf('%u', crc32($seed));
    }

    /**
     * @return array<int, int>
     */
    private function branchIds(): array
    {
        return collect($this->branches)->map(fn (Branch $branch) => $branch->id)->values()->all();
    }

    private function caregiverForBranch(int $branchId): User
    {
        foreach ($this->branches as $number => $branch) {
            if ($branch->id === $branchId) {
                return $this->caregivers[$number];
            }
        }

        // Falling back to a default caregiver here would quietly attribute a
        // whole branch's records to staff who do not work there, which is
        // exactly the kind of wrong-branch data this seeder exists to avoid.
        throw new RuntimeException("No demo caregiver is defined for branch {$branchId}.");
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function findResident(array $definition): ?Resident
    {
        return $this->demoResidents()->first(
            fn (Resident $resident) => $resident->first_name === $definition['first_name']
                && $resident->last_name === $definition['last_name']
                && $resident->room_number === $definition['room_number']
        );
    }

    /**
     * @return Collection<int, Resident>
     */
    private function demoResidents(): Collection
    {
        return $this->residents ??= Resident::withoutGlobalScopes()
            ->whereIn('branch_id', $this->branchIds())
            ->orderBy('id')
            ->get();
    }

    private function showSummary(): void
    {
        $this->command->line('');
        $this->command->line('Demo Facility Seed Summary');
        $this->command->line('  Facility: '.self::FACILITY_NAME.' (id '.$this->facility->id.')');

        foreach ($this->branches as $number => $branch) {
            $residents = Resident::withoutGlobalScopes()->where('branch_id', $branch->id)->count();
            $caregiver = $this->caregivers[$number];
            $this->command->line(
                "  Branch {$number}: {$branch->name} (id {$branch->id}) - {$residents} residents".
                " - caregiver {$caregiver->email}"
            );
        }

        $this->command->line('  Admin:      '.self::ADMIN_EMAIL.' / '.self::DEFAULT_PASSWORD);
        $this->command->line('  Caregiver1: '.self::CAREGIVER_ONE_EMAIL.' / '.self::DEFAULT_PASSWORD);
        $this->command->line('  Caregiver2: '.self::CAREGIVER_TWO_EMAIL.' / '.self::DEFAULT_PASSWORD);
        $this->command->line('  Residents:  '.$this->demoResidents()->count());
        $this->command->line('  Medications: '.Medication::withoutGlobalScopes()->whereIn('branch_id', $this->branchIds())->count());
        $this->command->line(
            '  Administrations: '.
            MedicationAdministration::withoutGlobalScopes()->whereIn('branch_id', $this->branchIds())->count().
            ' (the '.self::HISTORY_DAYS.' days before today)'
        );
        $this->command->line('');
        $this->command->warn('All demo accounts have location_check_bypass enabled so App Store reviewers can clock in from anywhere.');
    }
}
