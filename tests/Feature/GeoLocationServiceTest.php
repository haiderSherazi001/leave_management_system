<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GeoLocationService;
use DomainException;
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

    public function test_a_point_fifty_meters_away_is_within_the_configured_radius(): void
    {
        $service = $this->app->make(GeoLocationService::class);
        [$officeLat, $officeLon] = $this->officeCoordinates();
        [$lat, $lon] = $this->pointNorthOfOffice(50);

        $distance = $service->calculateDistanceInMeters($officeLat, $officeLon, $lat, $lon);

        $this->assertEqualsWithDelta(50.0, $distance, 0.5);
        $this->assertTrue($service->isWithinOfficeRadius($lat, $lon));
    }

    public function test_a_point_one_kilometer_away_is_outside_the_configured_radius(): void
    {
        $service = $this->app->make(GeoLocationService::class);
        [$officeLat, $officeLon] = $this->officeCoordinates();
        [$lat, $lon] = $this->pointNorthOfOffice(1000);

        $distance = $service->calculateDistanceInMeters($officeLat, $officeLon, $lat, $lon);

        $this->assertEqualsWithDelta(1000.0, $distance, 1.0);
        $this->assertFalse($service->isWithinOfficeRadius($lat, $lon));
    }

    /**
     * There's deliberately no .env fallback - HR must configure a real
     * office location via the admin screen before check-in can work at all.
     */
    public function test_it_throws_when_no_office_location_has_been_configured_yet(): void
    {
        $service = $this->app->make(GeoLocationService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Office location has not been configured yet. Please contact HR.');

        $service->isWithinOfficeRadius(31.5, 74.3);
    }

    /**
     * Seeds a real office_location row (idempotent - only the first call per
     * test actually inserts).
     *
     * @return array{0: float, 1: float}
     */
    private function officeCoordinates(): array
    {
        $lat = 31.411751;
        $lon = 73.117245;

        if (! DB::table('office_location')->exists()) {
            DB::table('office_location')->insert([
                'latitude' => $lat,
                'longitude' => $lon,
                'radius_meters' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$lat, $lon];
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
