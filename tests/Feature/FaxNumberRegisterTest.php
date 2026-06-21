<?php

namespace Tests\Feature;

use App\Models\FaxNumber;
use App\Models\FaxSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class FaxNumberRegisterTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    private function configureProvider(string $provider, array $credentials): FaxSetting
    {
        $settings = new FaxSetting([
            'facility_id' => $this->facility->id,
            'provider' => $provider,
            'credentials' => $credentials,
            'is_active' => true,
        ]);
        $settings->facility_id = $this->facility->id;
        $settings->webhook_secret = FaxSetting::generateWebhookSecret();
        $settings->save();

        return $settings;
    }

    public function test_register_existing_number_for_dashboard_managed_provider_creates_row(): void
    {
        $this->createAndActAs('administrator');
        $this->configureProvider('mfax', ['api_key' => 'zk_live_x']);

        $response = $this->postJson('/api/v1/fax/numbers/register', [
            'e164_number' => '+12534491960',
            'friendly_name' => 'Oasis main line',
        ]);

        $response->assertStatus(201);

        $number = FaxNumber::withoutGlobalScopes()
            ->where('provider', 'mfax')
            ->where('e164_number', '+12534491960')
            ->first();

        $this->assertNotNull($number);
        $this->assertSame($this->facility->id, $number->facility_id);
        $this->assertTrue((bool) $number->is_default, 'first number should become default');
        $this->assertTrue((bool) $number->is_active);
        $this->assertSame('Oasis main line', $number->friendly_name);
    }

    public function test_register_is_rejected_for_provider_that_supports_api_provisioning(): void
    {
        $this->createAndActAs('administrator');
        // Fake provider has no supportsNumberProvisioning() override → defaults to true.
        $this->configureProvider('fake', ['api_key' => 'x']);

        $response = $this->postJson('/api/v1/fax/numbers/register', [
            'e164_number' => '+12534491960',
        ]);

        $response->assertStatus(409);
        $this->assertSame(
            0,
            FaxNumber::withoutGlobalScopes()->where('e164_number', '+12534491960')->count(),
        );
    }

    public function test_register_rejects_duplicate_number(): void
    {
        $this->createAndActAs('administrator');
        $this->configureProvider('mfax', ['api_key' => 'zk_live_x']);

        $first = $this->postJson('/api/v1/fax/numbers/register', [
            'e164_number' => '+12534491960',
        ]);
        $first->assertStatus(201);

        $dup = $this->postJson('/api/v1/fax/numbers/register', [
            'e164_number' => '+12534491960',
        ]);

        $dup->assertStatus(409);
        $this->assertSame(
            1,
            FaxNumber::withoutGlobalScopes()->where('e164_number', '+12534491960')->count(),
        );
    }

    public function test_register_validates_e164_format(): void
    {
        $this->createAndActAs('administrator');
        $this->configureProvider('mfax', ['api_key' => 'zk_live_x']);

        $response = $this->postJson('/api/v1/fax/numbers/register', [
            'e164_number' => '2534491960', // missing +country
        ]);

        $response->assertStatus(422);
    }

    public function test_register_requires_configured_provider(): void
    {
        $this->createAndActAs('administrator');
        // No FaxSetting configured.

        $response = $this->postJson('/api/v1/fax/numbers/register', [
            'e164_number' => '+12534491960',
        ]);

        $response->assertStatus(409);
    }

    public function test_providers_endpoint_exposes_supports_number_provisioning(): void
    {
        $this->createAndActAs('administrator');

        $response = $this->getJson('/api/v1/fax/providers');
        $response->assertOk();

        $providers = collect($response->json('data.providers') ?? $response->json('data') ?? [])
            ->keyBy('key');

        $this->assertFalse($providers->get('mfax')['supports_number_provisioning']);
        $this->assertTrue($providers->get('ifax')['supports_number_provisioning']);
    }
}
