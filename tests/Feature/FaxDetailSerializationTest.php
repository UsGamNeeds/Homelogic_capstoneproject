<?php

namespace Tests\Feature;

use App\Models\Fax;
use App\Models\FaxNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class FaxDetailSerializationTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    public function test_show_returns_from_number_as_string_not_the_related_object(): void
    {
        $this->createAndActAs('administrator');

        $number = FaxNumber::create([
            'facility_id' => $this->facility->id,
            'provider' => 'mfax',
            'provider_number_id' => '+12534491960',
            'e164_number' => '+12534491960',
            'is_default' => true,
            'is_active' => true,
        ]);

        $fax = Fax::create([
            'facility_id' => $this->facility->id,
            'direction' => Fax::DIRECTION_OUTBOUND,
            'provider' => 'mfax',
            'provider_fax_id' => 'fax-uuid-1',
            'from_number' => '+12534491960',
            'from_number_id' => $number->id,
            'to_number' => '+18884732963',
            'status' => Fax::STATUS_QUEUED,
        ]);

        $response = $this->getJson('/api/v1/fax/'.$fax->id);
        $response->assertOk();

        // Regression: the fromNumber BelongsTo relation must not clobber the
        // string from_number column when serialized (it crashed the detail
        // drawer with React error #31 — rendering an object as a child).
        $from = $response->json('data.from_number');
        $this->assertIsString($from);
        $this->assertSame('+12534491960', $from);
        $this->assertSame('+18884732963', $response->json('data.to_number'));
    }
}
