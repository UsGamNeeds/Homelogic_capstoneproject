<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Notification;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class MedicationDueNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    private User $caregiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
        $this->caregiver = $this->makeUser('caregiver', $this->facility, $this->branch);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time, string $date = '2026-10-12'): void
    {
        Carbon::setTestNow(Carbon::parse("{$date} {$time}", config('app.timezone')));
    }

    private function makeUser(string $role, Facility $facility, Branch $branch): User
    {
        return User::factory()->create([
            'facility_id' => $facility->id,
            'assigned_branch_id' => $branch->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeResident(Branch $branch, ?User $caregiver, string $first = 'Ada'): Resident
    {
        $resident = Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => $first, 'last_name' => 'Test']);
        if ($caregiver) {
            Assignment::create([
                'caregiver_id' => $caregiver->id,
                'resident_id' => $resident->id,
                'branch_id' => $branch->id,
                'assigned_at' => now(),
                'assigned_by' => $caregiver->id,
                'is_active' => true,
            ]);
        }

        return $resident;
    }

    private function makeMed(Resident $resident, array $times, string $name = 'Aspirin'): Medication
    {
        $attrs = ['is_active' => true, 'start_date' => '2026-01-01'];
        foreach (array_values($times) as $i => $t) {
            $attrs['time_'.($i + 1)] = $t;
        }

        return Medication::create(array_merge([
            'resident_id' => $resident->id,
            'branch_id' => $resident->branch_id,
            'name' => $name,
            'instructions' => 'daily',
            'created_by' => $this->caregiver->id,
        ], $attrs));
    }

    private function run15(): void
    {
        $this->artisan('notifications:generate')->assertExitCode(0);
    }

    private function dueFor(User $user)
    {
        return Notification::withoutGlobalScopes()->where('user_id', $user->id)->where('type', 'medication_due')->get();
    }

    public function test_one_notification_per_caregiver_per_time(): void
    {
        $r1 = $this->makeResident($this->branch, $this->caregiver, 'Ada');
        $r2 = $this->makeResident($this->branch, $this->caregiver, 'Bo');
        $this->makeMed($r1, ['08:00:00', '20:00:00'], 'A');
        $this->makeMed($r1, ['08:00:00'], 'B');
        $this->makeMed($r2, ['08:00:00'], 'C');

        $this->at('07:50:00');
        $this->run15();

        $rows = $this->dueFor($this->caregiver);
        $this->assertCount(1, $rows);
        $n = $rows->first();
        $this->assertSame('Medications due at 8:00 AM', $n->title);
        $this->assertSame('3 doses for 2 residents', $n->message);
        $this->assertSame('/medications', $n->action_url);
        $this->assertSame('pill', $n->icon);
        $this->assertSame('08:00', $n->metadata['scheduled_time']);
        $this->assertSame('2026-10-12', $n->metadata['scheduled_date']);
        $this->assertSame(3, $n->metadata['count']);
        $this->assertCount(3, $n->metadata['medication_ids']);
        $this->assertEqualsCanonicalizing([$r1->id, $r2->id], $n->metadata['resident_ids']);
    }

    public function test_nothing_sent_outside_window(): void
    {
        $r = $this->makeResident($this->branch, $this->caregiver);
        $this->makeMed($r, ['08:00:00']);

        $this->at('07:30:00');
        $this->run15();
        $this->assertCount(0, $this->dueFor($this->caregiver), 'more than 15 min ahead');

        $this->at('08:06:00');
        $this->run15();
        $this->assertCount(0, $this->dueFor($this->caregiver), 'passed more than 5 min ago');

        $this->at('07:45:00');
        $this->run15();
        $this->assertCount(1, $this->dueFor($this->caregiver), 'upper bound is inclusive');
    }

    public function test_late_run_still_catches_just_passed_slot_once(): void
    {
        $r = $this->makeResident($this->branch, $this->caregiver);
        $this->makeMed($r, ['08:00:00']);

        $this->at('08:02:00');
        $this->run15();
        $this->assertCount(1, $this->dueFor($this->caregiver));

        $this->at('08:15:00');
        $this->run15();
        $this->assertCount(1, $this->dueFor($this->caregiver));
    }

    public function test_duplicate_assignments_do_not_double_count(): void
    {
        $r = $this->makeResident($this->branch, $this->caregiver);
        Assignment::create([
            'caregiver_id' => $this->caregiver->id,
            'resident_id' => $r->id,
            'branch_id' => $this->branch->id,
            'assigned_at' => now(),
            'assigned_by' => $this->caregiver->id,
            'is_active' => true,
        ]);
        $this->makeMed($r, ['08:00:00']);

        $this->at('07:50:00');
        $this->run15();

        $rows = $this->dueFor($this->caregiver);
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows->first()->metadata['count']);
        $this->assertSame('Give Aspirin to Ada Test', $rows->first()->message);
    }

    public function test_rerun_does_not_duplicate(): void
    {
        $r = $this->makeResident($this->branch, $this->caregiver);
        $this->makeMed($r, ['08:00:00']);

        $this->at('07:50:00');
        $this->run15();
        $this->at('07:55:00');
        $this->run15();
        $this->run15();

        $this->assertCount(1, $this->dueFor($this->caregiver));
    }

    public function test_dose_given_for_that_time_is_excluded_but_other_time_still_notifies(): void
    {
        $r = $this->makeResident($this->branch, $this->caregiver);
        $med = $this->makeMed($r, ['08:00:00', '12:00:00']);

        MedicationAdministration::create([
            'medication_id' => $med->id,
            'resident_id' => $r->id,
            'branch_id' => $this->branch->id,
            'administered_by' => $this->caregiver->id,
            'status' => 'completed',
            'administered_at' => Carbon::parse('2026-10-12 07:58:00', config('app.timezone')),
        ]);

        $this->at('07:50:00');
        $this->run15();
        $this->assertCount(0, $this->dueFor($this->caregiver), 'given 08:00 dose must not notify');

        $this->at('11:50:00');
        $this->run15();
        $rows = $this->dueFor($this->caregiver);
        $this->assertCount(1, $rows);
        $this->assertSame('12:00', $rows->first()->metadata['scheduled_time']);
    }

    public function test_single_dose_message_names_drug_and_resident(): void
    {
        $r = $this->makeResident($this->branch, $this->caregiver, 'Ada');
        $this->makeMed($r, ['08:00:00'], 'Aspirin');

        $this->at('07:50:00');
        $this->run15();

        $this->assertSame('Give Aspirin to Ada Test', $this->dueFor($this->caregiver)->first()->message);
    }

    public function test_unassigned_resident_goes_to_facility_admins_only(): void
    {
        $admin = $this->makeUser('administrator', $this->facility, $this->branch);
        $r = $this->makeResident($this->branch, null);
        $this->makeMed($r, ['08:00:00']);

        $this->at('07:50:00');
        $this->run15();

        $this->assertCount(1, $this->dueFor($admin));
        $this->assertCount(0, $this->dueFor($this->caregiver));
    }

    public function test_facility_isolation_and_no_mixed_summaries(): void
    {
        $otherFacility = Facility::factory()->create();
        $otherBranch = Branch::factory()->create(['facility_id' => $otherFacility->id]);
        $otherAdmin = $this->makeUser('administrator', $otherFacility, $otherBranch);
        $admin = $this->makeUser('administrator', $this->facility, $this->branch);

        $this->makeMed($this->makeResident($this->branch, null, 'Ada'), ['08:00:00']);
        $this->makeMed($this->makeResident($otherBranch, null, 'Zed'), ['08:00:00']);

        $this->at('07:50:00');
        $this->run15();

        $mine = $this->dueFor($admin);
        $theirs = $this->dueFor($otherAdmin);
        $this->assertCount(1, $mine);
        $this->assertCount(1, $theirs);
        $this->assertSame($this->facility->id, $mine->first()->facility_id);
        $this->assertSame($otherFacility->id, $theirs->first()->facility_id);
        $this->assertSame('Give Aspirin to Ada Test', $mine->first()->message);
        $this->assertSame('Give Aspirin to Zed Test', $theirs->first()->message);
    }

    public function test_time_just_after_midnight_is_found_before_midnight(): void
    {
        $r = $this->makeResident($this->branch, $this->caregiver);
        $this->makeMed($r, ['00:05:00']);

        $this->at('23:50:00');
        $this->run15();

        $rows = $this->dueFor($this->caregiver);
        $this->assertCount(1, $rows);
        $this->assertSame('2026-10-13', $rows->first()->metadata['scheduled_date']);
    }
}
