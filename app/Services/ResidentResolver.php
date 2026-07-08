<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resident;
use App\Models\User;

class ResidentResolver
{
    /**
     * Match resident names in the message against residents the user may see,
     * and redact matched names.
     *
     * Facility isolation is derived entirely from the active FacilityScope,
     * which reads the authenticated user's facility context. The caller MUST
     * invoke this method within that user's auth context (e.g. after
     * Sanctum::actingAs($user) or an authenticated request) so the scope
     * applies; the $user parameter itself is not used to re-derive scoping.
     */
    public function resolve(string $message, User $user): ResidentResolution
    {
        // FacilityScope limits this to the user's facility/branch automatically.
        $residents = Resident::query()->get(['id', 'first_name', 'last_name', 'branch_id']);

        // Phase 0: find residents whose FULL name literally appears in the message.
        // A full-name mention is unambiguous evidence of intent, so if any full-name
        // match exists we must not ALSO treat other residents as matched just because
        // part of the message happens to share their bare first or last name (e.g.
        // "Alice Williams" must not also match a resident named "Margaret Williams").
        $fullNameMatches = [];
        foreach ($residents as $resident) {
            $full = trim(($resident->first_name ?? '').' '.($resident->last_name ?? ''));
            if ($full === '') {
                continue;
            }
            $pattern = '/\b'.preg_quote($full, '/').'\b/i';
            if (preg_match($pattern, $message) === 1) {
                $fullNameMatches[$resident->id] = $resident;
            }
        }

        $matched = [];
        $namesToReplace = [];

        if ($fullNameMatches !== []) {
            // Only the full-name matches count. Still collect every name form
            // for those residents so a later bare mention in the same message
            // (e.g. "Mary" after "Mary Smith") is also redacted.
            foreach ($fullNameMatches as $resident) {
                $matched[$resident->id] = $resident;
                $full = trim(($resident->first_name ?? '').' '.($resident->last_name ?? ''));
                foreach (array_filter([$full, $resident->first_name, $resident->last_name]) as $candidate) {
                    $namesToReplace[] = $candidate;
                }
            }
        } else {
            // No full name was typed - fall back to bare first/last name matching
            // (e.g. "meds for Mary" is ambiguous across every resident named Mary).
            foreach ($residents as $resident) {
                $candidates = array_filter([$resident->first_name, $resident->last_name]);

                foreach ($candidates as $candidate) {
                    $pattern = '/\b'.preg_quote((string) $candidate, '/').'\b/i';
                    if (preg_match($pattern, $message) === 1) {
                        $matched[$resident->id] = $resident;
                        $namesToReplace[] = $candidate;
                    }
                }
            }
        }

        // Phase 2 (replacement): replace ALL collected candidate strings,
        // longest first, so full names redact before bare first/last names.
        // A doubled "{RESIDENT} {RESIDENT}" is acceptable; residual PII is not.
        $namesToReplace = array_values(array_unique($namesToReplace));
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
