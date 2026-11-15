<?php

namespace App\Services\AiAssistant;

/**
 * Last-line-of-defense regex scan for identifiers that should never appear in
 * text sent to or received from the Anthropic API. Redaction of the known
 * resident name happens earlier (see ProgressNoteAgentService::redact), but
 * this catches anything that slipped through — another resident's name typed
 * into the shift entry, a phone number, an email, an SSN-shaped string, or
 * the model echoing something it shouldn't.
 */
class IdentifierScanner
{
    /** @return array<int, array{type: string, match: string}> */
    public static function scan(string $text, array $knownNames = []): array
    {
        $hits = [];

        if (preg_match_all('/\b\d{3}-\d{2}-\d{4}\b/', $text, $m)) {
            foreach ($m[0] as $match) {
                $hits[] = ['type' => 'ssn_like', 'match' => $match];
            }
        }

        if (preg_match_all('/\b[\w.+-]+@[\w-]+\.[a-zA-Z]{2,}\b/', $text, $m)) {
            foreach ($m[0] as $match) {
                $hits[] = ['type' => 'email', 'match' => $match];
            }
        }

        if (preg_match_all('/\b(?:\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/', $text, $m)) {
            foreach ($m[0] as $match) {
                $hits[] = ['type' => 'phone_like', 'match' => $match];
            }
        }

        foreach (array_filter($knownNames) as $name) {
            $name = trim((string) $name);
            if ($name === '' || mb_strlen($name) < 3) {
                continue;
            }

            if (stripos($text, $name) !== false) {
                $hits[] = ['type' => 'known_name', 'match' => $name];
            }
        }

        return $hits;
    }

    /** Replace known names with {RESIDENT}/{PERSON} without otherwise touching the text. */
    public static function redactKnownNames(string $text, array $names): string
    {
        foreach (array_filter($names) as $name) {
            $name = trim((string) $name);
            if ($name === '' || mb_strlen($name) < 3) {
                continue;
            }

            $text = preg_replace('/'.preg_quote($name, '/').'/i', '{RESIDENT}', $text) ?? $text;

            $parts = array_filter(explode(' ', $name));
            foreach ($parts as $part) {
                if (mb_strlen($part) >= 3) {
                    $text = preg_replace('/\b'.preg_quote($part, '/').'\b/i', '{RESIDENT}', $text) ?? $text;
                }
            }
        }

        return $text;
    }
}
