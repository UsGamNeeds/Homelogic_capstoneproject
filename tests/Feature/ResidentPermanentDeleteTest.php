<?php

namespace Tests\Feature;

use App\Models\ResidentStatusEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class ResidentPermanentDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    public function test_administrator_can_permanently_delete_a_resident(): void
    {
        $this->createAndActAs('administrator');
        $resident = $this->createResident();

        $response = $this->deleteJson("/api/v1/residents/{$resident->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('residents', ['id' => $resident->id]);
    }

    public function test_deleting_a_resident_removes_their_related_records(): void
    {
        $user = $this->createAndActAs('administrator');
        $resident = $this->createResident();

        ResidentStatusEvent::create([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'facility_id' => $this->facility->id,
            'status_type' => 'lifecycle',
            'from_status' => 'active',
            'to_status' => 'discharged',
            'effective_at' => now(),
        ]);

        DB::table('vital_signs')->insert([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'taken_by' => $user->id,
            'measurement_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('resident_documents')->insert([
            'resident_id' => $resident->id,
            'document_name' => 'Test document',
            'document_type' => 'other',
            'file_path' => 'resident-documents/missing-file.pdf',
            'file_name' => 'missing-file.pdf',
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/residents/{$resident->id}")->assertOk();

        $this->assertDatabaseMissing('residents', ['id' => $resident->id]);
        $this->assertSame(0, DB::table('resident_status_events')->where('resident_id', $resident->id)->count());
        $this->assertSame(0, DB::table('vital_signs')->where('resident_id', $resident->id)->count());
        $this->assertSame(0, DB::table('resident_documents')->where('resident_id', $resident->id)->count());
    }

    public function test_caregiver_cannot_delete_a_resident(): void
    {
        $this->createAndActAs('caregiver');
        $resident = $this->createResident();

        $this->deleteJson("/api/v1/residents/{$resident->id}")->assertStatus(403);

        $this->assertDatabaseHas('residents', ['id' => $resident->id]);
    }

    public function test_resident_from_another_facility_cannot_be_deleted(): void
    {
        $otherFacility = \App\Models\Facility::factory()->create();
        $otherBranch = \App\Models\Branch::factory()->create(['facility_id' => $otherFacility->id]);
        $foreignResident = $this->createResident($otherBranch);

        $this->createAndActAs('administrator');

        $this->deleteJson("/api/v1/residents/{$foreignResident->id}")->assertStatus(403);

        $this->assertDatabaseHas('residents', ['id' => $foreignResident->id]);
    }
}
