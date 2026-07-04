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

        $matched = [];
        $namesToReplace = [];

        // Phase 1 (detection, no mutation): test every candidate form against
        // the ORIGINAL message and collect ALL matching forms for ALL matched
        // residents. Do not stop at the first hit per resident, or other
        // occurrences/forms of that resident's name would survive redaction.
        foreach ($residents as $resident) {
            $full = trim(($resident->first_name ?? '').' '.($resident->last_name ?? ''));
            $candidates = array_filter([
                $full,
                $resident->first_name,
                $resident->last_name,
            ]);

            foreach ($candidates as $candidate) {
                $pattern = '/\b'.preg_quote((string) $candidate, '/').'\b/i';
                if (preg_match($pattern, $message) === 1) {
                    $matched[$resident->id] = $resident;
                    $namesToReplace[] = $candidate;
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
