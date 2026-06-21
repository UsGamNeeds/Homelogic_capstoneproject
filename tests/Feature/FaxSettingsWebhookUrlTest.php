<?php

namespace Tests\Feature;

use App\Models\FaxSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class FaxSettingsWebhookUrlTest extends TestCase
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

    public function test_mfax_returns_shared_account_wide_webhook_url(): void
    {
        config(['fax.mfax.webhook_secret' => 'env-mfax-routing-secret']);

        $this->createAndActAs('administrator');
        $settings = $this->configureProvider('mfax', ['api_key' => 'zk_live_x']);

        $response = $this->getJson('/api/v1/fax/settings/webhook-url');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertTrue($data['shared_across_facilities']);
        $this->assertStringContainsString('/api/webhooks/fax/mfax/env-mfax-routing-secret', $data['url']);
        // Must NOT leak the per-facility secret for a shared provider.
        $this->assertStringNotContainsString($settings->getRawOriginal('webhook_secret'), $data['url']);
    }

    public function test_telnyx_returns_per_facility_webhook_url(): void
    {
        $this->createAndActAs('administrator');
        $settings = $this->configureProvider('telnyx', ['api_key' => 'x']);

        $response = $this->getJson('/api/v1/fax/settings/webhook-url');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertFalse($data['shared_across_facilities']);
        $this->assertStringContainsString(
            '/api/webhooks/fax/telnyx/'.$settings->getRawOriginal('webhook_secret'),
            $data['url'],
        );
    }
}
