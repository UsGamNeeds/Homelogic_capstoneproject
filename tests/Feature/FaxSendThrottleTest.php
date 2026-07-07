<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * Batch 4 — the fax send endpoint is a paid action (each page costs money).
 * Without a rate limit a single compromised token can loop it. The route must
 * throttle; the limiter runs before the controller, so requests are rejected
 * with 429 once over the limit regardless of payload validity.
 */
class FaxSendThrottleTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    public function test_fax_send_is_rate_limited(): void
    {
        [$facility, $branch] = $this->createFacilityAndBranch();
        $this->createAndActAs('administrator', $facility, $branch);

        $statuses = [];
        for ($i = 0; $i < 25; $i++) {
            $statuses[] = $this->postJson('/api/v1/fax/send', [])->getStatusCode();
        }

        $this->assertNotSame(429, $statuses[0], 'First request must not be throttled');
        $this->assertContains(429, $statuses, 'Fax send must return 429 once the per-user limit is exceeded');
    }
}
