<?php

declare(strict_types=1);

namespace App\Services;

final class GeoLocationService
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    public function __construct(
        private readonly OfficeLocationService $officeLocation,
    ) {}

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
     * HR-configured office_location row wins when one exists; falls back to
     * the OFFICE_LATITUDE/OFFICE_LONGITUDE/MAX_CHECKIN_DISTANCE_METERS .env
     * defaults otherwise, so nothing breaks before HR has ever visited the
     * "Office Location" admin screen.
     */
    public function isWithinOfficeRadius(float $latitude, float $longitude): bool
    {
        $office = $this->officeLocation->get();

        $officeLat = $office->latitude ?? (float) config('attendance.office_latitude');
        $officeLon = $office->longitude ?? (float) config('attendance.office_longitude');
        $radius = $office->radius_meters ?? (int) config('attendance.max_checkin_distance_meters');

        $distance = $this->calculateDistanceInMeters($latitude, $longitude, $officeLat, $officeLon);

        return $distance <= $radius;
    }
}
