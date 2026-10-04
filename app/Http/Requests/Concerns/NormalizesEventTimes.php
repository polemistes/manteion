<?php

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Event times are stored as UTC wall-clock values. The app sends UTC
 * instants ("…Z"), but a time with another offset ("…+01:00") would
 * otherwise be stored as written, an hour off; so each is converted to UTC
 * first. Anything that doesn't parse is left for the rules to reject.
 */
trait NormalizesEventTimes
{
    protected function prepareForValidation(): void
    {
        foreach (['starts_at', 'ends_at'] as $key) {
            $value = $this->input($key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                $this->merge([$key => CarbonImmutable::parse($value)->utc()->toDateTimeString()]);
            } catch (Throwable) {
                // Not a date: the `date` rule says so.
            }
        }
    }
}
