<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\EventTimeZoneMode;
use App\Services\TimeZones;
use Illuminate\Foundation\Http\FormRequest;

/**
 * For the controllers that save what the event form sends: an event, or a
 * template of one.
 */
trait DecidesEventTimeZone
{
    /**
     * The time zone of an event (or a template) and how it was decided.
     * The form types times as local to a zone and sends that zone; without
     * one (another client), an automatic event takes the zone at the pin
     * it sent. A zone chosen by hand is never replaced by the pin's, and
     * an event with no local time keeps its host's zone for its days. What
     * the form leaves out stays as it was (`$keptMode` and `$keptZone`,
     * null for something new).
     *
     * @return array{timezone: string, timezone_mode: EventTimeZoneMode}
     */
    protected function timeZoneFrom(FormRequest $request, ?EventTimeZoneMode $keptMode, ?string $keptZone): array
    {
        $mode = EventTimeZoneMode::tryFrom((string) $request->validated('timezone_mode'))
            ?? $keptMode
            ?? EventTimeZoneMode::Automatic;
        $sent = $request->validated('timezone');
        $kept = $keptZone ?? $request->user()->displayTimeZone();

        if ($mode !== EventTimeZoneMode::Automatic) {
            return ['timezone' => $sent ?? $kept, 'timezone_mode' => $mode];
        }

        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');

        return [
            'timezone' => $sent ?? TimeZones::forPlace(
                $latitude === null ? null : (float) $latitude,
                $longitude === null ? null : (float) $longitude,
                $request->validated('country_code'),
            ) ?? $kept,
            'timezone_mode' => $mode,
        ];
    }
}
