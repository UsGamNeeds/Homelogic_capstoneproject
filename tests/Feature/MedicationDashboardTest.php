<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * The medication dashboard is the slowest endpoint the mobile app calls, and the
 * compliance figure on the admin home screen is the one number an administrator
 * is accountable for. So these tests pin the arithmetic down first and the cost
 * second: every count below was recorded against the original implementation
 * before it was rewritten, and the rewrite is only allowed to change how long
 * the answer takes to produce.
 */
class MedicationDashboardTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    private User $user;

    private Resident $alice;

    private Resident $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00', config('app.timezone')));

        $this->createFacilityAndBranch();
        $this->user = $this->createAndActAs('administrator');

        $this->alice = $this->createResident();
        $this->bob = $this->createResident();

        // Four scheduled slots a day across three medications, active all week.
        $aspirin = $this->medication($this->alice, 'Aspirin', ['08:00:00', '20:00:00']);
        $metformin = $this->medication($this->alice, 'Metformin', ['09:00:00']);
        $warfarin = $this->medication($this->bob, 'Warfarin', ['08:00:00']);

        // Today: two given, one missed, one still due at 20:00.
        $this->administration($aspirin, '2026-09-24 08:05:00', 'completed');
        $this->administration($metformin, '2026-09-24 09:10:00', 'completed');
        $this->administration($warfarin, '2026-09-24 08:00:00', 'missed');

        // Three days back, so the trend is not flat.
        $this->administration($aspirin, '2026-09-21 08:02:00', 'completed');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_reports_the_counts_behind_the_compliance_figure(): void
    {
        $response = $this->getJson('/api/v1/medications/dashboard')->assertOk();

        $this->assertSame([
            'scheduled' => 4,
            'administered' => 2,
            'missed' => 1,
            'refused' => 0,
            'adherence' => 50,
            'active_medications' => 3,
        ], $response->json('today'));
    }

    public function test_adherence_trend_covers_seven_days_ending_today(): void
    {
        $trend = $this->getJson('/api/v1/medications/dashboard')->assertOk()->json('adherence_trend');

        $this->assertCount(7, $trend);
        $this->assertSame('2026-09-18', $trend[0]['date']);
        $this->assertSame('2026-09-24', $trend[6]['date']);

        // Every day carries the same four scheduled slots, so only the numerator moves.
        $this->assertSame([4, 4, 4, 4, 4, 4, 4], array_column($trend, 'scheduled'));
        $this->assertSame([0, 0, 0, 1, 0, 0, 2], array_column($trend, 'administered'));
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], array_column($trend, 'missed'));
        $this->assertSame([0, 0, 0, 25, 0, 0, 50], array_column($trend, 'adherence'));
        $this->assertSame('Mon', $trend[3]['day']);
    }

    public function test_the_other_sections_still_answer(): void
    {
        $response = $this->getJson('/api/v1/medications/dashboard')->assertOk();

        // Aspirin's second slot is the only dose left today, and 20:00 is more
        // than four hours from noon, so nothing is upcoming yet.
        $this->assertSame([], $response->json('upcoming'));
        $this->assertCount(1, $response->json('missed_today'));
        $this->assertSame('Warfarin', $response->json('missed_today.0.medication_name'));
        $this->assertCount(4, $response->json('recent_activity'));

        $summary = $response->json('resident_summary');
        $this->assertCount(2, $summary);
        // Sorted by missed doses first, so Bob leads.
        $this->assertSame($this->bob->id, $summary[0]['resident_id']);
        $this->assertSame(1, $summary[0]['missed_today']);
        $this->assertSame(2, $summary[1]['administered_today']);

        $this->assertSame(0, $response->json('delivery_status.today_count'));
    }

    /**
     * The defect this branch exists for.
     *
     * The dashboard used to lazy-load each medication's administrations inside a
     * per-medication loop, and to rebuild the whole active-medication set once
     * per day of the trend. Cost therefore grew with the size of the home, which
     * is the one direction it must not grow: the facilities that wait longest
     * are the ones with the most residents.
     */
    public function test_query_count_does_not_grow_with_the_number_of_medications(): void
    {
        // Warm up first, so one-off lookups do not land in the measured call.
        $this->getJson('/api/v1/medications/dashboard')->assertOk();

        $withThree = $this->countQueries(fn () => $this->getJson('/api/v1/medications/dashboard')->assertOk());

        for ($i = 0; $i < 9; $i++) {
            $this->medication($this->alice, 'Filler '.$i, ['08:00:00']);
        }

        $withTwelve = $this->countQueries(fn () => $this->getJson('/api/v1/medications/dashboard')->assertOk());

        $this->assertSame(
            $withThree,
            $withTwelve,
            "Adding nine medications changed the query count from {$withThree} to {$withTwelve}."
        );
    }

    public function test_administrations_are_read_only_for_the_window_being_reported(): void
    {
        $ancient = $this->medication($this->alice, 'Long Standing', ['08:00:00']);
        for ($i = 30; $i < 120; $i++) {
            $this->administration($ancient, Carbon::parse('2026-09-24')->subDays($i)->setTime(8, 2)->toDateTimeString(), 'completed');
        }

        $queries = $this->sqlLog(fn () => $this->getJson('/api/v1/medications/dashboard')->assertOk());

        $touchingAdministrations = array_values(array_filter(
            $queries,
            fn ($sql) => str_contains($sql, 'from "medication_administrations"')
        ));

        $this->assertNotEmpty($touchingAdministrations);

        // The regression this guards: the per-medication eager load used to be
        // unbounded, so every medication dragged its entire history back on
        // every dashboard load.
        $eagerLoad = array_values(array_filter(
            $touchingAdministrations,
            fn ($sql) => str_contains($sql, '"medication_id" in')
        ));

        $this->assertCount(1, $eagerLoad, 'Administrations should be loaded once, for all medications at once.');
        $this->assertStringContainsString('between', strtolower($eagerLoad[0]));

        // And nothing else may read the table without either a date constraint
        // or a row limit holding it down.
        foreach ($touchingAdministrations as $sql) {
            $bounded = preg_match('/administered_at.{0,60}(between|>=|<=)/i', $sql)
                || preg_match('/administered_at.{0,60}\)?\s*=/i', $sql)
                || preg_match('/\blimit\b/i', $sql);

            $this->assertTrue(
                (bool) $bounded,
                'A medication_administrations query is neither dated nor limited, so it grows without end: '.$sql
            );
        }
    }

    public function test_only_returns_just_the_requested_sections(): void
    {
        $response = $this->getJson('/api/v1/medications/dashboard?only=today,adherence_trend')->assertOk();

        $this->assertSame(['adherence_trend', 'today'], $this->sortedKeys($response->json()));
        $this->assertSame(2, $response->json('today.administered'));
        $this->assertCount(7, $response->json('adherence_trend'));
    }

    public function test_only_is_cheaper_than_the_full_payload(): void
    {
        $this->getJson('/api/v1/medications/dashboard')->assertOk();

        $full = $this->countQueries(fn () => $this->getJson('/api/v1/medications/dashboard')->assertOk());
        $sliced = $this->countQueries(fn () => $this->getJson('/api/v1/medications/dashboard?only=today')->assertOk());

        $this->assertLessThan($full, $sliced);
    }

    public function test_omitting_only_still_returns_every_section(): void
    {
        $response = $this->getJson('/api/v1/medications/dashboard')->assertOk();

        $this->assertSame([
            'adherence_trend',
            'delivery_status',
            'missed_today',
            'recent_activity',
            'resident_summary',
            'today',
            'upcoming',
        ], $this->sortedKeys($response->json()));
    }

    public function test_only_rejects_a_non_string_value_instead_of_erroring(): void
    {
        $this->getJson('/api/v1/medications/dashboard?only[]=today')
            ->assertStatus(422)
            ->assertJsonValidationErrors('only');
    }

    public function test_only_rejects_a_value_that_names_no_section(): void
    {
        $this->getJson('/api/v1/medications/dashboard?only=,,')
            ->assertStatus(422)
            ->assertJsonValidationErrors('only');
    }

    /**
     * The day-by-day branch the rewrite replaced.
     *
     * A medication that starts or stops inside the reported week is the only
     * case where the per-day scheduled count moves, and so the only case that
     * tells the single-pass rewrite apart from the per-day queries it replaced.
     */
    public function test_trend_counts_each_day_against_the_medications_running_that_day(): void
    {
        // Starts on the 22nd: absent from the first four days of the trend.
        $this->medication($this->alice, 'Late Starter', ['08:00:00'], ['start_date' => '2026-09-22']);

        // Stopped on the 20th: present for the first three days only.
        $this->medication($this->bob, 'Ended Early', ['08:00:00'], ['end_date' => '2026-09-20']);

        $trend = $this->getJson('/api/v1/medications/dashboard')->assertOk()->json('adherence_trend');

        $this->assertSame(
            [5, 5, 5, 4, 5, 5, 5],
            array_column($trend, 'scheduled'),
            'Scheduled slots must follow which medications were actually running on each day.'
        );

        // The 21st is the day both extra medications sit outside.
        $this->assertSame('2026-09-21', $trend[3]['date']);
        $this->assertSame(25, $trend[3]['adherence']);
        // Today now has five slots against two doses given.
        $this->assertSame(40, $trend[6]['adherence']);
    }

    public function test_an_unknown_section_is_rejected_rather_than_silently_ignored(): void
    {
        $this->getJson('/api/v1/medications/dashboard?only=today,tomorrow')
            ->assertStatus(422)
            ->assertJsonValidationErrors('only');
    }

    public function test_only_sent_as_a_json_body_on_a_get_is_handled(): void
    {
        // Laravel validates against the JSON body here but the parameter is
        // read from the query string, so the two bags must not disagree.
        $this->call('GET', '/api/v1/medications/dashboard', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['only' => 'today']))
            ->assertOk()
            ->assertJsonStructure(['today']);
    }

    public function test_only_returns_the_same_numbers_as_the_full_payload(): void
    {
        $full = $this->getJson('/api/v1/medications/dashboard')->assertOk()->json();
        $sliced = $this->getJson('/api/v1/medications/dashboard?only=today,adherence_trend')->assertOk()->json();

        $this->assertSame($full['today'], $sliced['today']);
        $this->assertSame($full['adherence_trend'], $sliced['adherence_trend']);
    }

    /**
     * The change reworked how medications and administrations are queried, and
     * the administrations table carries no facility scope of its own — it is
     * reached only through medication ids that were already branch-filtered. So
     * the branch boundary is worth asserting directly rather than trusting.
     */
    public function test_a_caregiver_sees_only_their_own_branch(): void
    {
        $otherBranch = \App\Models\Branch::factory()->create(['facility_id' => $this->facility->id]);
        $otherResident = $this->createResident($otherBranch);

        $theirs = Medication::create([
            'resident_id' => $otherResident->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Other Branch Tablet',
            'instructions' => 'oral',
            'time_1' => '08:00:00',
            'created_by' => $this->user->id,
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        MedicationAdministration::create([
            'medication_id' => $theirs->id,
            'resident_id' => $otherResident->id,
            'branch_id' => $otherBranch->id,
            'administered_by' => $this->user->id,
            'administered_at' => Carbon::parse('2026-09-24 08:05:00', config('app.timezone')),
            'status' => 'completed',
        ]);

        // An administrator covers the whole facility: both branches.
        $asAdmin = $this->getJson('/api/v1/medications/dashboard')->assertOk();
        $this->assertSame(4, $asAdmin->json('today.active_medications'));
        $this->assertSame(3, $asAdmin->json('today.administered'));

        // A caregiver is pinned to their assigned branch.
        $this->createAndActAs('caregiver', $this->facility, $this->branch);
        $asCaregiver = $this->getJson('/api/v1/medications/dashboard')->assertOk();

        $this->assertSame(3, $asCaregiver->json('today.active_medications'));
        $this->assertSame(2, $asCaregiver->json('today.administered'));

        $residentNames = array_column($asCaregiver->json('resident_summary'), 'resident_id');
        $this->assertNotContains($otherResident->id, $residentNames);

        foreach ($asCaregiver->json('recent_activity') as $entry) {
            $this->assertNotSame('Other Branch Tablet', $entry['medication_name']);
        }
    }

    public function test_a_user_with_no_branch_gets_empty_sections_not_an_error(): void
    {
        $stranded = User::factory()->create([
            'facility_id' => null,
            'assigned_branch_id' => null,
            'role' => 'administrator',
            'is_active' => true,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($stranded, ['*']);

        $response = $this->getJson('/api/v1/medications/dashboard')->assertOk();

        $this->assertSame(0, $response->json('today.scheduled'));
        $this->assertSame([], $response->json('upcoming'));
    }

    private function medication(Resident $resident, string $name, array $times, array $overrides = []): Medication
    {
        $attributes = [
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'name' => $name,
            'instructions' => 'With water',
            'created_by' => $this->user->id,
            'is_active' => true,
            'start_date' => '2024-01-01',
        ];

        foreach ($times as $index => $time) {
            $attributes['time_'.($index + 1)] = $time;
        }

        return Medication::create(array_merge($attributes, $overrides));
    }

    private function administration(Medication $medication, string $at, string $status): MedicationAdministration
    {
        return MedicationAdministration::create([
            'medication_id' => $medication->id,
            'resident_id' => $medication->resident_id,
            'branch_id' => $this->branch->id,
            'administered_by' => $this->user->id,
            'administered_at' => Carbon::parse($at, config('app.timezone')),
            'status' => $status,
        ]);
    }

    private function countQueries(callable $call): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $call();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @return array<int, string>
     */
    private function sqlLog(callable $call): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $call();

        $sql = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $sql;
    }

    private function sortedKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);

        return $keys;
    }
}
