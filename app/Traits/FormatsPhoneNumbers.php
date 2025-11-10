<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait FormatsPhoneNumbers
{
    protected function sanitizePhoneNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    protected function formatPhoneNumber(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        }

        return $value;
    }

    protected function phoneAttribute(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->formatPhoneNumber($value),
            set: fn ($value) => $this->sanitizePhoneNumber($value)
        );
    }
}

