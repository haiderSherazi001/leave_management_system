<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GeoLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeoLocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EARTH_RADIUS_METERS = 6371000.0;

    public function test_coordinates_exactly_on_the_office_are_zero_meters_away_and_within_radius(): void
    {
        $service = $this->app->make(GeoLocationService::class);
        [$officeLat, $officeLon] = $this->officeCoordinates();

        $distance = $service->calculateDistanceInMeters($officeLat, $officeLon, $officeLat, $officeLon);

        $this->assertEqualsWithDelta(0.0, $distance, 0.001);
        $this->assertTrue($service->isWithinOfficeRadius($officeLat, $officeLon));
    }

    public function test_a_point_fifty_meters_away_is_within_the_default_radius(): void
    {
        $service = $this->app->make(GeoLocationService::class);
        [$officeLat, $officeLon] = $this->officeCoordinates();
        [$lat, $lon] = $this->pointNorthOfOffice(50);

        $distance = $service->calculateDistanceInMeters($officeLat, $officeLon, $lat, $lon);

        $this->assertEqualsWithDelta(50.0, $distance, 0.5);
        $this->assertTrue($service->isWithinOfficeRadius($lat, $lon));
    }

    public function test_a_point_one_kilometer_away_is_outside_the_default_radius(): void
    {
        $service = $this->app->make(GeoLocationService::class);
        [$officeLat, $officeLon] = $this->officeCoordinates();
        [$lat, $lon] = $this->pointNorthOfOffice(1000);

        $distance = $service->calculateDistanceInMeters($officeLat, $officeLon, $lat, $lon);

        $this->assertEqualsWithDelta(1000.0, $distance, 1.0);
        $this->assertFalse($service->isWithinOfficeRadius($lat, $lon));
    }

    public function test_an_hr_configured_office_location_overrides_the_env_default(): void
    {
        $service = $this->app->make(GeoLocationService::class);
        [$configuredLat, $configuredLon] = $this->officeCoordinates();

        // A DB-configured office 1000m north of the .env default - a point
        // near the .env default should now read as *outside* the radius,
        // proving the DB row actually won, not the fallback.
        $deltaLatDegrees = rad2deg(1000 / self::EARTH_RADIUS_METERS);
        DB::table('office_location')->insert([
            'latitude' => $configuredLat + $deltaLatDegrees,
            'longitude' => $configuredLon,
            'radius_meters' => 100,
            'label' => 'Head Office',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($service->isWithinOfficeRadius($configuredLat, $configuredLon));
        $this->assertTrue($service->isWithinOfficeRadius($configuredLat + $deltaLatDegrees, $configuredLon));
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function officeCoordinates(): array
    {
        return [(float) config('attendance.office_latitude'), (float) config('attendance.office_longitude')];
    }

    /**
     * A point due north of the configured office by the given distance. For
     * a pure north/south offset (same longitude), the Haversine formula
     * reduces exactly to distance = R * deltaLatRadians, which makes this a
     * reliable way to construct a coordinate at a known distance without an
     * external geocoding fixture.
     *
     * @return array{0: float, 1: float}
     */
    private function pointNorthOfOffice(float $meters): array
    {
        [$officeLat, $officeLon] = $this->officeCoordinates();

        $deltaLatDegrees = rad2deg($meters / self::EARTH_RADIUS_METERS);

        return [$officeLat + $deltaLatDegrees, $officeLon];
    }
}
