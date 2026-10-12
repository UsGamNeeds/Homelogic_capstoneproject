<?php

namespace Tests\Feature;

use App\Exceptions\UserHasProtectedRecordsException;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * A caregiver's signature on a medication dose is a clinical and legal record.
 * It must outlive their employment: a regulator auditing a MAR two years from
 * now still needs to see who gave what, even if that person left the home.
 *
 * users is referenced by 41 cascading foreign keys, so a hard delete of a user
 * row would take that history with it. These tests pin down that deletion is
 * refused for anyone with history, while still allowing an account created by
 * mistake to be tidied away.
 */
class UserDeletionPreservesClinicalRecordsTest extends TestCase
{
    use RefreshDatabase, SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->createFacilityAndBranch();
    }

    private function makeUser(string $role = 'caregiver', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * The medication is created by a DIFFERENT user (the admin) so this test
     * isolates one reference only: medication_administrations.administered_by.
     * medications.created_by is protected too, which would otherwise make it
     * ambiguous which reference did the blocking.
     */
    public function test_deleting_a_caregiver_with_medication_records_is_refused_and_the_dose_survives(): void
    {
        $admin = $this->makeUser('administrator');
        $caregiver = $this->makeUser('caregiver', ['name' => 'Departed Caregiver']);
        $resident = Resident::factory()->create(['branch_id' => $this->branch->id]);

        $medicationId = DB::table('medications')->insertGetId([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'name' => 'Paracetamol 500mg',
            'created_by' => $admin->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $doseId = DB::table('medication_administrations')->insertGetId([
            'medication_id' => $medicationId,
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'administered_by' => $caregiver->id,
            'administered_at' => now(),
            'status' => 'completed',
            'dosage_given' => '500mg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $caregiver->delete();
            $this->fail('Expected the delete to be refused, but it went through.');
        } catch (UserHasProtectedRecordsException $e) {
            $this->assertStringContainsString('medication administrations', $e->getMessage());
        }

        // Queried through DB rather than the model so we assert on the actual
        // row, not on something a global scope merely hid from us.
        $this->assertTrue(
            DB::table('medication_administrations')->where('id', $doseId)->exists(),
            'The medication administration record was destroyed. A signed dose is a legal record.'
        );

        $this->assertTrue(
            DB::table('users')->where('id', $caregiver->id)->exists(),
            'The caregiver row was deleted despite the guard.'
        );
    }

    public function test_deleting_a_caregiver_with_incidents_is_refused_and_the_incident_survives(): void
    {
        $caregiver = $this->makeUser();
        $resident = Resident::factory()->create(['branch_id' => $this->branch->id]);

        $incidentId = DB::table('incidents')->insertGetId([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'incident_type' => 'fall',
            'description' => 'Resident found on floor beside bed.',
            'incident_date' => now(),
            'severity' => 'high',
            'reported_by' => $caregiver->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(UserHasProtectedRecordsException::class);

        try {
            $caregiver->delete();
        } finally {
            $this->assertTrue(
                DB::table('incidents')->where('id', $incidentId)->exists(),
                'The incident the caregiver reported was destroyed with them.'
            );
        }
    }

    /**
     * The escape hatch. An account created by mistake five minutes ago has no
     * history behind it, so admins must still be able to clear it up - otherwise
     * the guard just leaves junk in the staff list with no way to remove it.
     */
    public function test_a_user_with_no_history_can_still_be_deleted(): void
    {
        $mistake = $this->makeUser('caregiver', ['name' => 'Typo Account']);

        $this->assertFalse($mistake->hasProtectedRecords());

        $mistake->delete();

        $this->assertFalse(
            DB::table('users')->where('id', $mistake->id)->exists(),
            'An account with no history should still be deletable.'
        );
    }

    /**
     * A user's own settings are not history anyone needs to keep. If these
     * counted as protected then every account would be undeletable, because
     * every account accumulates them.
     */
    public function test_a_users_own_notifications_do_not_block_deletion(): void
    {
        $user = $this->makeUser();

        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'type' => 'medication_due',
            'title' => 'Medication due',
            'message' => 'Paracetamol due at 14:00',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(
            $user->hasProtectedRecords(),
            'A personal notification should not make an account undeletable.'
        );
        // Memoised, so re-read the row to ask again after the assertion above.
        $user = $user->fresh();

        $user->delete();

        $this->assertFalse(DB::table('users')->where('id', $user->id)->exists());
    }

    /**
     * hasProtectedRecords() is asked once per row when rendering staff lists, so
     * it must stop at the first reference it finds and remember the answer.
     * Counting every protected table on every call made a single check take over
     * a minute in this suite.
     */
    public function test_checking_for_protected_records_stops_early_and_is_memoised(): void
    {
        $caregiver = $this->makeUser();
        $resident = Resident::factory()->create(['branch_id' => $this->branch->id]);

        DB::table('appointments')->insert([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'title' => 'GP review',
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'created_by' => $caregiver->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Warm the schema lookups so we measure the record checks, not the
        // one-off introspection that is cached per request.
        $this->makeUser()->hasProtectedRecords();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertTrue($caregiver->hasProtectedRecords());
        $firstCall = count(DB::getQueryLog());

        $this->assertLessThan(
            count(User::PROTECTED_RECORD_REFERENCES),
            $firstCall,
            'The check should stop at the first reference found, not scan every protected table.'
        );

        DB::flushQueryLog();
        $caregiver->hasProtectedRecords();

        $this->assertCount(
            0,
            DB::getQueryLog(),
            'A repeated check should reuse the memoised answer rather than query again.'
        );

        DB::disableQueryLog();
    }

    /**
     * The API must answer with a usable message, not a 500 from the model guard.
     */
    public function test_the_delete_endpoint_refuses_with_a_helpful_message(): void
    {
        $admin = $this->makeUser('administrator');
        $caregiver = $this->makeUser();
        $resident = Resident::factory()->create(['branch_id' => $this->branch->id]);

        DB::table('incidents')->insert([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'incident_type' => 'fall',
            'description' => 'Resident found on floor beside bed.',
            'incident_date' => now(),
            'severity' => 'high',
            'reported_by' => $caregiver->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($admin, ['*']);
        app()->instance('facility', $this->facility);

        $this->deleteJson("/api/v1/users/{$caregiver->id}")
            ->assertStatus(422)
            ->assertJsonPath('records.incidents', 1)
            ->assertJsonFragment(['message' => 'This user has care records that must be kept, so their account '
                .'cannot be deleted. Deactivate them instead - they will no longer be able '
                .'to sign in, and their records stay intact.']);

        $this->assertTrue(DB::table('users')->where('id', $caregiver->id)->exists());
    }

    /**
     * Deactivation is what admins are told to use instead of deletion, so the
     * behaviour it relies on is pinned here rather than assumed.
     */
    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $user = $this->makeUser('caregiver', [
            'email' => 'departed@test.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'departed@test.com',
            'password' => 'password123',
        ])->assertOk();

        $user->update(['is_active' => false]);

        $this->postJson('/api/v1/login', [
            'email' => 'departed@test.com',
            'password' => 'password123',
        ])->assertStatus(403);
    }
}
