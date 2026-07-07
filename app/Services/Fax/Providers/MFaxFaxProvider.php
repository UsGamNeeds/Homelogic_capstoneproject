<?php

namespace App\Services\Fax\Providers;

use App\Services\Fax\Contracts\FaxProvider;
use App\Services\Fax\Support\CredentialField;
use App\Services\Fax\Support\InboundFax;
use App\Services\Fax\Support\SendFaxRequest;
use App\Services\Fax\Support\SendResult;
use App\Services\Fax\Support\TestResult;
use App\Services\Fax\Support\WebhookResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * mfax.to programmable fax driver.
 *
 * Docs: https://developers.mfax.to (OpenAPI at /openapi.json)
 * HIPAA-compliant cloud fax. NOTE: this is mfax.to — a distinct vendor from
 * "Documo (mFax)" (api.documo.com), which has its own provider class.
 *
 * One business API key (zk_live_…) serves the whole organization. mfax allows
 * a single account-wide webhook URL, so — like iFax — inbound events are routed
 * centrally by the destination toNumber and outbound status by provider_fax_id.
 *
 * The webhook signing secret and the per-account webhook routing secret are
 * stored in app config (config/fax.php → mfax), NOT per facility, because the
 * single account spans all facilities.
 */
class MFaxFaxProvider implements FaxProvider
{
    private const BASE_URL = 'https://developers.mfax.to/v1';

    public function __construct(private array $credentials) {}

    public static function key(): string
    {
        return 'mfax';
    }

    public static function displayName(): string
    {
        return 'mfax.to';
    }

    public static function description(): ?string
    {
        return 'HIPAA-compliant cloud fax (developers.mfax.to). One API key serves all facilities.';
    }

    public static function credentialSchema(): array
    {
        return [
            new CredentialField(
                name: 'api_key',
                label: 'API Key',
                type: CredentialField::TYPE_SECRET,
                help: 'Business API key from the mfax dashboard (Developers → API keys). Starts with "zk_live_". The same key serves every facility.',
                placeholder: 'zk_live_…',
            ),
        ];
    }

    public static function sharedWebhookUrl(): ?string
    {
        $secret = (string) config('fax.mfax.webhook_secret', '');

        return $secret !== ''
            ? url('/api/webhooks/fax/'.self::key().'/'.$secret)
            : null;
    }

    public static function usesSharedWebhook(): bool
    {
        return true;
    }

    /**
     * mfax does not expose number provisioning via its API; numbers are bought
     * in the mfax dashboard and registered into Evergreen by their E.164 value.
     */
    public static function supportsNumberProvisioning(): bool
    {
        return false;
    }

    private function http(): PendingRequest
    {
        $apiKey = $this->credentials['api_key'] ?? '';
        if (! $apiKey) {
            throw new RuntimeException('mfax API key missing.');
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(30)
            ->baseUrl(self::BASE_URL);
    }

    public function testConnection(): TestResult
    {
        try {
            // Short, explicit timeouts so a blocked/slow egress fails fast with a
            // clear message instead of hanging until the web server returns 503.
            $response = $this->http()
                ->connectTimeout(8)
                ->timeout(12)
                ->get('/faxes', ['limit' => 1]);

            if ($response->successful()) {
                return TestResult::ok('mfax API reachable.');
            }

            return TestResult::fail(
                'HTTP '.$response->status().' from mfax: '.($response->json('message') ?? $response->body()),
            );
        } catch (\Throwable $e) {
            return TestResult::fail('Unable to reach mfax: '.$e->getMessage());
        }
    }

    public function send(SendFaxRequest $request): SendResult
    {
        if (! is_file($request->filePath)) {
            return SendResult::fail("Source file not found: {$request->filePath}");
        }

        // mfax's send endpoint only accepts `to` + `file`; the caller-id/from
        // number and subject are implicit to the account and not supported.
        try {
            $response = $this->http()
                ->attach('file', (string) file_get_contents($request->filePath), basename($request->filePath))
                ->asMultipart()
                ->post('/faxes', [
                    'to' => $request->toNumber,
                ]);
        } catch (\Throwable $e) {
            return SendResult::fail('mfax send failed: '.$e->getMessage());
        }

        if ($response->failed()) {
            return SendResult::fail(
                'mfax rejected fax: '.($response->json('message') ?? $response->body()),
                $response->json() ?? [],
            );
        }

        return SendResult::ok(
            providerFaxId: (string) ($response->json('uuid') ?? ''),
            status: $this->mapStatus((string) ($response->json('status') ?? 'queued')),
            raw: $response->json() ?? [],
        );
    }

    public function searchAvailableNumbers(?string $areaCode = null, string $country = 'US', int $limit = 10): array
    {
        // mfax does not expose number provisioning via the API; numbers are
        // managed in the dashboard. Return nothing rather than fake results.
        return [];
    }

    public function purchaseNumber(string $e164Number): \App\Services\Fax\Support\NumberInfo
    {
        throw new RuntimeException('mfax does not support number provisioning via API. Provision numbers in the mfax dashboard.');
    }

    public function releaseNumber(string $providerNumberId): bool
    {
        throw new RuntimeException('mfax does not support number provisioning via API. Manage numbers in the mfax dashboard.');
    }

    public function verifyWebhookSignature(Request $request): void
    {
        $secret = (string) config('fax.mfax.signing_secret', '');
        if ($secret === '') {
            throw new RuntimeException('mfax signing secret not configured.');
        }

        $header = (string) $request->header('X-Zelda-Signature', '');
        if ($header === '') {
            throw new RuntimeException('Missing X-Zelda-Signature header.');
        }

        [$timestamp, $signature] = $this->parseSignatureHeader($header);
        if ($timestamp === null || $signature === '') {
            throw new RuntimeException('Malformed X-Zelda-Signature header.');
        }

        $tolerance = (int) config('fax.webhook_timestamp_tolerance', 300);
        if (abs(time() - $timestamp) > $tolerance) {
            throw new RuntimeException('mfax webhook timestamp outside tolerance window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('mfax webhook signature mismatch.');
        }
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $payload = $request->json()->all();
        if ($payload === []) {
            return WebhookResult::ignore();
        }

        $type = (string) ($payload['type'] ?? '');
        $eventId = isset($payload['id']) ? (string) $payload['id'] : null;
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $uuid = (string) ($data['uuid'] ?? '');

        if ($uuid === '') {
            return WebhookResult::ignore($payload);
        }

        if ($type === 'fax.received') {
            return WebhookResult::inbound(
                new InboundFax(
                    providerFaxId: $uuid,
                    fromNumber: $this->normalizeE164($data['from'] ?? ''),
                    toNumber: $this->normalizeE164($data['to'] ?? ''),
                    mediaUrl: $data['media_url'] ?? null,
                    pageCount: isset($data['page_count']) ? (int) $data['page_count'] : null,
                    receivedAt: $this->parseTimestamp($data['created_at'] ?? null),
                    raw: $payload,
                ),
                eventId: $eventId,
                raw: $payload,
            );
        }

        if ($type === 'fax.delivered' || $type === 'fax.failed') {
            $status = $this->mapStatus((string) ($data['status'] ?? ($type === 'fax.failed' ? 'failed' : 'delivered')));

            return WebhookResult::status(
                providerFaxId: $uuid,
                newStatus: $status,
                reason: $data['failure_reason'] ?? null,
                eventId: $eventId,
                raw: $payload,
                pageCount: isset($data['page_count']) ? (int) $data['page_count'] : null,
            );
        }

        return WebhookResult::ignore($payload);
    }

    public function downloadInboundMedia(InboundFax $inbound): string
    {
        if ($inbound->mediaBytes !== null && $inbound->mediaBytes !== '') {
            return $inbound->mediaBytes;
        }

        if (! $inbound->mediaUrl) {
            return '';
        }

        // media_url is a presigned, time-limited link — fetch it without the
        // Bearer token so the presigned auth is not disturbed.
        $response = Http::timeout(30)->get($inbound->mediaUrl);

        if ($response->failed()) {
            throw new RuntimeException('Failed to download mfax media: HTTP '.$response->status());
        }

        return $response->body();
    }

    /**
     * Map an mfax lifecycle status onto an Evergreen Fax status. mfax uses the
     * same vocabulary (queued/sending/delivered/failed), so this is mostly a
     * whitelist guarding against unexpected values.
     */
    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'queued' => 'queued',
            'sending' => 'sending',
            'delivered' => 'delivered',
            'failed' => 'failed',
            default => 'queued',
        };
    }

    /**
     * @return array{0: ?int, 1: string}
     */
    private function parseSignatureHeader(string $header): array
    {
        $timestamp = null;
        $signature = '';

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$k, $v] = $pair;
            if ($k === 't') {
                $timestamp = is_numeric($v) ? (int) $v : null;
            } elseif ($k === 'v1') {
                $signature = $v;
            }
        }

        return [$timestamp, $signature];
    }

    private function parseTimestamp(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? (new \DateTimeImmutable)->setTimestamp((int) $value)
                : new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeE164(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }

        return str_starts_with((string) $number, '+') ? (string) $number : '+'.$digits;
    }
}
