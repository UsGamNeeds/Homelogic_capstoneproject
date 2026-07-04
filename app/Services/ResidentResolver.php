<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resident;
use App\Models\User;

class ResidentResolver
{
    /**
     * Match resident names in the message against residents the user may see
     * (FacilityScope applies automatically), and redact matched names.
     */
    public function resolve(string $message, User $user): ResidentResolution
    {
        // FacilityScope limits this to the user's facility/branch automatically.
        $residents = Resident::query()->get(['id', 'first_name', 'last_name', 'branch_id']);

        $matched = [];
        $namesToReplace = [];

        // Collect all matches against the original message.
        foreach ($residents as $resident) {
            $full = trim(($resident->first_name ?? '').' '.($resident->last_name ?? ''));
            $candidates = array_filter([
                $full,
                $resident->first_name,
                $resident->last_name,
            ]);

            // Longest candidate first so "Mary Smith" matches before "Mary".
            usort($candidates, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

            foreach ($candidates as $candidate) {
                $pattern = '/\b'.preg_quote((string) $candidate, '/').'\b/i';
                if (preg_match($pattern, $message) === 1) {
                    $matched[$resident->id] = $resident;
                    $namesToReplace[] = $candidate;
                    break; // one hit per resident is enough
                }
            }
        }

        // Replace all matched names, longest first to avoid partial replacements.
        usort($namesToReplace, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        $redacted = $message;
        foreach ($namesToReplace as $name) {
            $pattern = '/\b'.preg_quote((string) $name, '/').'\b/i';
            $redacted = preg_replace($pattern, '{RESIDENT}', $redacted);
        }

        $matches = array_values($matched);
        $status = match (true) {
            count($matches) === 0 => 'none',
            count($matches) === 1 => 'one',
            default => 'ambiguous',
        };

        return new ResidentResolution($matches, $redacted, $status);
    }
}
