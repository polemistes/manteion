<?php

namespace App\Services;

use DateTimeZone;

/**
 * Which clock a place keeps. Times are stored as UTC instants; a person
 * reads them in their own time zone (User::displayTimeZone()), and an
 * event also has the zone of the place it happens in
 * (Event::localTimeZone()), found here from its map pin.
 */
class TimeZones
{
    /** @var array<string, array{0: float, 1: float}>|null */
    private static ?array $referencePoints = null;

    /**
     * The time zone at a pin. A country with one zone answers by itself;
     * in one with several (or without a country), it is the zone whose
     * reference city lies nearest: right for nearly every place, and
     * needing no map of the zones' borders. Null when there is nothing to
     * go by.
     */
    public static function forPlace(?float $latitude, ?float $longitude, ?string $countryCode): ?string
    {
        $inCountry = self::inCountry($countryCode);

        if (count($inCountry) === 1) {
            return $inCountry[0];
        }

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $nearest = null;
        $nearestDistance = INF;

        foreach (self::referencePoints() as $zone => [$zoneLatitude, $zoneLongitude]) {
            if ($inCountry !== [] && ! in_array($zone, $inCountry, true)) {
                continue;
            }

            $distance = self::distance($latitude, $longitude, $zoneLatitude, $zoneLongitude);

            if ($distance < $nearestDistance) {
                $nearest = $zone;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }

    /**
     * @return list<string>
     */
    private static function inCountry(?string $countryCode): array
    {
        if ($countryCode === null || preg_match('/^[A-Za-z]{2}$/', $countryCode) !== 1) {
            return [];
        }

        return DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, strtoupper($countryCode));
    }

    /**
     * Every zone's reference city, as the time zone database places it.
     *
     * @return array<string, array{0: float, 1: float}>
     */
    private static function referencePoints(): array
    {
        if (self::$referencePoints !== null) {
            return self::$referencePoints;
        }

        $points = [];

        foreach (DateTimeZone::listIdentifiers() as $zone) {
            $location = (new DateTimeZone($zone))->getLocation();

            // UTC and the like belong to no country and sit at 0, 0.
            if ($location === false || $location['country_code'] === '??') {
                continue;
            }

            $points[$zone] = [(float) $location['latitude'], (float) $location['longitude']];
        }

        return self::$referencePoints = $points;
    }

    /**
     * The angle between two points (haversine), which orders them as the
     * distance would.
     */
    private static function distance(float $latitude, float $longitude, float $otherLatitude, float $otherLongitude): float
    {
        $latitudeDelta = deg2rad($otherLatitude - $latitude);
        $longitudeDelta = deg2rad($otherLongitude - $longitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($otherLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 2 * asin(min(1.0, sqrt($a)));
    }
}
