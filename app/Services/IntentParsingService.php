<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntentParsingService
{
    private const INTENTS = ['todays_meds', 'prn_status', 'unknown'];

    private const SYSTEM_PROMPT =
        'You classify a care-home staff request into exactly one label: '
        ."todays_meds (they want a resident's medications scheduled for today), "
        ."prn_status (they want a resident's as-needed/PRN medications), or "
        ."unknown (anything else). The resident's name is redacted as {RESIDENT}. "
        .'Reply with ONLY the label, nothing else.';

    /**
     * Local regex classification. Returns null when no confident match.
     */
    public function fastPath(string $text): ?string
    {
        $t = strtolower($text);

        if (preg_match('/\bprn\b|as needed/', $t) === 1) {
            return 'prn_status';
        }

        if (preg_match('/\b(med|meds|medication|medications|tablet|tablets|drug|drugs|dose|doses)\b/', $t) === 1) {
            return 'todays_meds';
        }

        return null;
    }

    /**
     * Classify intent. Uses the local fast-path first; falls back to the LLM.
     * Always returns a valid enum value; fails closed to 'unknown'.
     */
    public function parse(string $redactedText): string
    {
        $fast = $this->fastPath($redactedText);
        if ($fast !== null) {
            return $fast;
        }

        return $this->classifyWithLlm($redactedText);
    }

    private function classifyWithLlm(string $redactedText): string
    {
        $apiKey = config('services.anthropic.api_key');
        if (empty($apiKey)) {
            return 'unknown';
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => 16,
                'system' => self::SYSTEM_PROMPT,
                'messages' => [
                    ['role' => 'user', 'content' => $redactedText],
                ],
            ]);

            if ($response->failed()) {
                return 'unknown';
            }

            $label = strtolower(trim((string) $response->json('content.0.text', '')));

            return in_array($label, self::INTENTS, true) ? $label : 'unknown';
        } catch (\Throwable $e) {
            Log::warning('Assistant intent parse failed', ['error' => $e->getMessage()]);

            return 'unknown';
        }
    }
}
