<?php

namespace Tests\Feature;

use App\Services\ChartAssistantService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChartAssistantServicePhiGuardTest extends TestCase
{
    private const RESIDENT_NAME = 'Prudence Okonkwo-Vantessen';

    private function payload(): array
    {
        return [
            'resident' => ['name' => self::RESIDENT_NAME],
            'window' => 'last 14 days',
            'vitals' => [
                'count' => 8,
                'critical_count' => 1,
                'warning_count' => 2,
                'latest' => [
                    'temperature' => 99.8,
                    'pulse' => 92,
                    'oxygen_saturation' => 95,
                    'status' => 'pending_review',
                ],
            ],
            'sleep' => [
                'count' => 4,
                'average_hours' => 6.2,
                'average_quality' => 5.5,
            ],
            'behavior_charts' => [
                'count' => 3,
                'provider_notified_count' => 1,
                'log_count' => 4,
            ],
            'appointments' => [
                'upcoming_count' => 2,
            ],
            'medications' => [
                'active_count' => 3,
                'missed_count' => 1,
                'administration_count' => 5,
            ],
            'faxes' => [
                'count' => 0,
                'messages' => [],
            ],
        ];
    }

    public function test_disabled_flag_skips_outbound_call_even_with_key_present(): void
    {
        config(['services.anthropic.enabled' => false]);
        config(['services.anthropic.api_key' => 'sk-test-key-present']);

        Http::fake();

        $result = app(ChartAssistantService::class)->analyze($this->payload(), null);

        Http::assertNothingSent();
        $this->assertSame('heuristic', $result['mode']);
        $this->assertSame('heuristic', $result['model']);
        $this->assertNotEmpty($result['summary']);
    }

    public function test_enabled_flag_with_key_sends_redacted_request(): void
    {
        config(['services.anthropic.enabled' => true]);
        config(['services.anthropic.api_key' => 'sk-test-key-present']);
        config(['services.anthropic.model' => 'claude-haiku-4-5-20251001']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['text' => json_encode([
                        'summary' => 'Trends look stable.',
                        'insights' => ['Routine monitoring is sufficient.'],
                        'recommendations' => ['Continue current care plan.'],
                    ])],
                ],
            ], 200),
        ]);

        $result = app(ChartAssistantService::class)->analyze($this->payload(), 'Summarize trends.');

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, '{RESIDENT}')
                && ! str_contains($body, self::RESIDENT_NAME);
        });

        $this->assertSame('anthropic', $result['mode']);
        $this->assertSame('claude-haiku-4-5-20251001', $result['model']);
    }

    public function test_array_valued_summary_does_not_throw_and_produces_string(): void
    {
        config(['services.anthropic.enabled' => true]);
        config(['services.anthropic.api_key' => 'sk-test-key-present']);
        config(['services.anthropic.model' => 'claude-haiku-4-5-20251001']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['text' => json_encode([
                        'summary' => ['Vitals are stable.', 'No urgent follow-up needed.'],
                        'insights' => ['Routine monitoring is sufficient.'],
                        'recommendations' => ['Continue current care plan.'],
                    ])],
                ],
            ], 200),
        ]);

        $result = app(ChartAssistantService::class)->analyze($this->payload(), 'Summarize trends.');

        $this->assertSame('anthropic', $result['mode']);
        $this->assertIsString($result['summary']);
        $this->assertStringContainsString('Vitals are stable.', $result['summary']);
        $this->assertStringContainsString('No urgent follow-up needed.', $result['summary']);
    }
}
