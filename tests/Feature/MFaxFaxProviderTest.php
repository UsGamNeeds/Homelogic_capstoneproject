<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Fax;
use App\Models\FaxNumber;
use App\Models\FaxSetting;
use App\Services\Fax\Providers\MFaxFaxProvider;
use App\Services\Fax\Support\SendFaxRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MFaxFaxProviderTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-mfax-webhook-secret';

    private const SIGNING_SECRET = 'whsec_test_mfax_signing';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fax.mfax.webhook_secret' => self::WEBHOOK_SECRET,
            'fax.mfax.signing_secret' => self::SIGNING_SECRET,
            'fax.disk' => 'local',
        ]);
    }

    /**
     * Build the X-Zelda-Signature header for a raw body, matching mfax's
     * scheme: HMAC-SHA256(secret, "<ts>.<raw_body>") hex, formatted "t=,v1=".
     */
    private function signHeader(string $rawBody, ?int $timestamp = null, ?string $secret = null): string
    {
        $ts = $timestamp ?? time();
        $secret ??= self::SIGNING_SECRET;
        $hex = hash_hmac('sha256', "{$ts}.{$rawBody}", $secret);

        return "t={$ts},v1={$hex}";
    }

    /**
     * POST a signed webhook with the exact raw body that was signed.
     */
    private function postSignedWebhook(array $payload, ?string $signature = null)
    {
        $raw = json_encode($payload);
        $signature ??= $this->signHeader($raw);

        return $this->call(
            'POST',
            '/api/webhooks/fax/mfax/'.self::WEBHOOK_SECRET,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_ZELDA_SIGNATURE' => $signature,
            ],
            $raw,
        );
    }

    public function test_test_connection_succeeds_when_mfax_api_is_reachable(): void
    {
        Http::fake([
            'https://developers.mfax.to/v1/faxes*' => Http::response([], 200),
        ]);

        $provider = new MFaxFaxProvider(['api_key' => 'zk_live_key']);
        $result = $provider->testConnection();

        $this->assertTrue($result->ok);
    }

    public function test_send_fax_returns_uuid_from_mfax_response(): void
    {
        $path = storage_path('app/faxes/mfax-test.pdf');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, '%PDF-1.4');

        Http::fake([
            'https://developers.mfax.to/v1/faxes' => Http::response([
                'uuid' => '8f3c1e2a-0000-4444-aaaa-000000000001',
                'to' => '+12065550999',
                'status' => 'queued',
                'direction' => 'outbound',
            ], 202),
        ]);

        $provider = new MFaxFaxProvider(['api_key' => 'zk_live_key']);
        $result = $provider->send(new SendFaxRequest(
            fromNumber: '+12065550100',
            toNumber: '+12065550999',
            filePath: $path,
            subject: 'Refill',
        ));

        $this->assertTrue($result->ok);
        $this->assertSame('8f3c1e2a-0000-4444-aaaa-000000000001', $result->providerFaxId);
        $this->assertSame('queued', $result->status);

        @unlink($path);
    }

    public function test_send_fax_fails_on_quota_exceeded(): void
    {
        $path = storage_path('app/faxes/mfax-test-2.pdf');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, '%PDF-1.4');

        Http::fake([
            'https://developers.mfax.to/v1/faxes' => Http::response([
                'code' => 'quota_exceeded',
                'message' => 'monthly page quota exhausted',
            ], 402),
        ]);

        $provider = new MFaxFaxProvider(['api_key' => 'zk_live_key']);
        $result = $provider->send(new SendFaxRequest(
            fromNumber: '+12065550100',
            toNumber: '+12065550999',
            filePath: $path,
        ));

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('quota', strtolower($result->message));

        @unlink($path);
    }

    public function test_status_webhook_updates_matching_outbound_fax(): void
    {
        Event::fake();

        $facility = Facility::factory()->create();
        $settings = new FaxSetting([
            'facility_id' => $facility->id,
            'provider' => 'mfax',
            'credentials' => ['api_key' => 'zk_live_key'],
            'is_active' => true,
        ]);
        $settings->facility_id = $facility->id;
        $settings->webhook_secret = FaxSetting::generateWebhookSecret();
        $settings->save();

        $fax = Fax::create([
            'facility_id' => $facility->id,
            'direction' => Fax::DIRECTION_OUTBOUND,
            'provider' => 'mfax',
            'provider_fax_id' => '8f3c1e2a-0000-4444-aaaa-000000000010',
            'from_number' => '+12065550100',
            'to_number' => '+12065550999',
            'status' => Fax::STATUS_QUEUED,
        ]);

        $response = $this->postSignedWebhook([
            'id' => 'evt_status_1',
            'type' => 'fax.delivered',
            'created' => time(),
            'data' => [
                'uuid' => '8f3c1e2a-0000-4444-aaaa-000000000010',
                'to' => '+12065550999',
                'status' => 'delivered',
                'direction' => 'outbound',
                'page_count' => 2,
            ],
        ]);

        $response->assertOk();
        $this->assertSame(Fax::STATUS_DELIVERED, $fax->refresh()->status);
    }

    public function test_inbound_webhook_routes_to_facility_by_destination_number(): void
    {
        Event::fake();
        Storage::fake('local');

        Http::fake([
            'https://media.mfax.to/*' => Http::response('%PDF-1.4 inbound', 200),
        ]);

        $facility = Facility::factory()->create();
        $settings = new FaxSetting([
            'facility_id' => $facility->id,
            'provider' => 'mfax',
            'credentials' => ['api_key' => 'zk_live_key'],
            'is_active' => true,
        ]);
        $settings->facility_id = $facility->id;
        $settings->webhook_secret = FaxSetting::generateWebhookSecret();
        $settings->save();

        FaxNumber::create([
            'facility_id' => $facility->id,
            'provider' => 'mfax',
            'provider_number_id' => 'mfax-num-1',
            'e164_number' => '+12065550100',
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->postSignedWebhook([
            'id' => 'evt_inbound_1',
            'type' => 'fax.received',
            'created' => time(),
            'data' => [
                'uuid' => '8f3c1e2a-0000-4444-aaaa-000000000020',
                'to' => '+12065550100',
                'status' => 'delivered',
                'direction' => 'inbound',
                'page_count' => 3,
                'media_url' => 'https://media.mfax.to/presigned/inbound.pdf',
            ],
        ]);

        $response->assertOk();

        $fax = Fax::withoutGlobalScopes()
            ->where('provider', 'mfax')
            ->where('provider_fax_id', '8f3c1e2a-0000-4444-aaaa-000000000020')
            ->first();

        $this->assertNotNull($fax);
        $this->assertSame($facility->id, $fax->facility_id);
        $this->assertSame(Fax::DIRECTION_INBOUND, $fax->direction);
        $this->assertSame(3, $fax->page_count);
    }

    public function test_webhook_rejects_tampered_body(): void
    {
        $payload = [
            'id' => 'evt_tampered',
            'type' => 'fax.delivered',
            'created' => time(),
            'data' => ['uuid' => 'x', 'status' => 'delivered'],
        ];

        // Sign one body, then send a different one.
        $signature = $this->signHeader(json_encode($payload));
        $payload['data']['status'] = 'failed';

        $response = $this->postSignedWebhook($payload, $signature);

        $response->assertStatus(401);
    }

    public function test_webhook_rejects_stale_timestamp(): void
    {
        $payload = [
            'id' => 'evt_stale',
            'type' => 'fax.delivered',
            'created' => time(),
            'data' => ['uuid' => 'x', 'status' => 'delivered'],
        ];

        $raw = json_encode($payload);
        // 10 minutes old — beyond the 5-minute replay window.
        $signature = $this->signHeader($raw, time() - 600);

        $response = $this->postSignedWebhook($payload, $signature);

        $response->assertStatus(401);
    }

    public function test_search_available_numbers_returns_empty_without_api_support(): void
    {
        $provider = new MFaxFaxProvider(['api_key' => 'zk_live_key']);

        $this->assertSame([], $provider->searchAvailableNumbers('206'));
    }
}
