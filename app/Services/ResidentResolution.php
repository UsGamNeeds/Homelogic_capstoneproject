<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resident;

final class ResidentResolution
{
    /**
     * @param  array<int, Resident>  $matches
     */
    public function __construct(
        public readonly array $matches,
        public readonly string $redacted,
        public readonly string $status,
    ) {}
}
