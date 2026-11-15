<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class StaffClockInLocationBypassTest extends TestCase
{
    use RefreshDatabase, SetupFacility;

    // Banjul, Gambia - thousands of km from the Portland, OR branch/facility
    // used in these tests, and well outside the 50 metre check-in radius.
    private const FAR_AWAY_LATITUDE = 13.4549;

    private const FAR_AWAY_LONGITUDE = -16.5790;

    // Portland, OR - used as the branch/facility coordinates in tests that
    // configure coordinates.
    private const BRANCH_LATITUDE = 45.5152;

    private const BRANCH_LONGITUDE = -122.6784;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->createFacilityAndBranch();
    }

    private function actingAsStaff(bool $bypass, bool $withCoordinates = true): User
    {
        if ($withCoordinates) {
            $this->branch->update([
                'latitude' => self::BRANCH_LATITUDE,
                'longitude' => self::BRANCH_LONGITUDE,
            ]);
        }

        $user = User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'caregiver',
            'is_active' => true,
            'location_check_bypass' => $bypass,
        ]);

        Sanctum::actingAs($user, ['*']);
        app()->instance('facility', $this->facility);

        return $user;
    }

    public function test_bypass_enabled_allows_clock_in_from_far_away_coordinates(): void
    {
        $this->actingAsStaff(bypass: true);

        $response = $this->postJson('/api/v1/staff/clock-in', [
            'latitude' => self::FAR_AWAY_LATITUDE,
            'longitude' => self::FAR_AWAY_LONGITUDE,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'clock_in']);
    }

    public function test_bypass_disabled_rejects_clock_in_from_far_away_coordinates(): void
    {
        $this->actingAsStaff(bypass: false);

        $response = $this->postJson('/api/v1/staff/clock-in', [
            'latitude' => self::FAR_AWAY_LATITUDE,
            'longitude' => self::FAR_AWAY_LONGITUDE,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'distance_exceeded']);
    }

    public function test_bypass_enabled_allows_clock_in_when_branch_and_facility_have_no_coordinates(): void
    {
        // Neither factory sets coordinates by default, so leaving them
        // untouched reproduces the "no location configured" path.
        $this->actingAsStaff(bypass: true, withCoordinates: false);

        $response = $this->postJson('/api/v1/staff/clock-in', [
            'latitude' => self::FAR_AWAY_LATITUDE,
            'longitude' => self::FAR_AWAY_LONGITUDE,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'clock_in']);
    }

    public function test_bypass_disabled_rejects_clock_in_when_branch_and_facility_have_no_coordinates(): void
    {
        $this->actingAsStaff(bypass: false, withCoordinates: false);

        $response = $this->postJson('/api/v1/staff/clock-in', [
            'latitude' => self::FAR_AWAY_LATITUDE,
            'longitude' => self::FAR_AWAY_LONGITUDE,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'no_location_configured']);
    }
}
