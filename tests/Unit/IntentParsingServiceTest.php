<?php

namespace Tests\Unit;

use App\Services\IntentParsingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntentParsingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.api_key' => 'test-key']);
    }

    private function service(): IntentParsingService
    {
        return new IntentParsingService;
    }

    public function test_fastpath_detects_prn(): void
    {
        $this->assertSame('prn_status', $this->service()->fastPath('any prn for {RESIDENT}'));
    }

    public function test_fastpath_detects_todays_meds(): void
    {
        $this->assertSame('todays_meds', $this->service()->fastPath('what meds is {RESIDENT} on today'));
    }

    public function test_fastpath_returns_null_when_unclear(): void
    {
        $this->assertNull($this->service()->fastPath('hello there'));
    }

    public function test_parse_uses_fastpath_without_calling_llm(): void
    {
        Http::fake(); // any real call would be recorded
        $result = $this->service()->parse('prn for {RESIDENT}');
        $this->assertSame('prn_status', $result);
        Http::assertNothingSent();
    }

    public function test_parse_calls_llm_when_fastpath_misses(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'todays_meds']],
            ], 200),
        ]);
        $result = $this->service()->parse('give me the rundown for {RESIDENT}');
        $this->assertSame('todays_meds', $result);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.anthropic.com'));
    }

    public function test_parse_fails_closed_to_unknown_on_llm_error(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('', 500)]);
        $this->assertSame('unknown', $this->service()->parse('mumble mumble {RESIDENT}'));
    }

    public function test_parse_maps_unexpected_llm_output_to_unknown(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'banana']],
            ], 200),
        ]);
        $this->assertSame('unknown', $this->service()->parse('mumble {RESIDENT}'));
    }

    public function test_parse_fails_closed_when_api_key_missing(): void
    {
        config(['services.anthropic.api_key' => null]);
        Http::fake();
        $this->assertSame('unknown', $this->service()->parse('mumble mumble {RESIDENT}'));
        Http::assertNothingSent();
    }
}
