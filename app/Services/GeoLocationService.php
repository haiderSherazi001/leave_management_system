<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

final class GeoLocationService
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    public function __construct(
        private readonly OfficeLocationService $officeLocation,
    ) {}

    /**
     * @throws DomainException if HR hasn't configured an office location yet
     */
    public function office(): object
    {
        $office = $this->officeLocation->get();

        if ($office === null) {
            throw new DomainException('Office location has not been configured yet. Please contact HR.');
        }

        return $office;
    }

    /**
     * Great-circle distance between two coordinates, via the Haversine
     * formula. Accurate enough for office-radius checks at this scale —
     * the ~0.3% error versus Earth's actual (slightly ellipsoidal) shape is
     * negligible next to a 100-meter geofence.
     */
    public function calculateDistanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * @throws DomainException if HR hasn't configured an office location yet
     */
    public function isWithinOfficeRadius(float $latitude, float $longitude): bool
    {
        $office = $this->office();

        $distance = $this->calculateDistanceInMeters($latitude, $longitude, $office->latitude, $office->longitude);

        return $distance <= $office->radius_meters;
    }
}
