<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class OfficeLocationService
{
    public function get(): ?object
    {
        $location = DB::table('office_location')->first();

        if ($location === null) {
            return null;
        }

        return (object) [
            'id' => $location->id,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'radius_meters' => (int) $location->radius_meters,
            'label' => $location->label,
        ];
    }

    public function save(float $latitude, float $longitude, int $radiusMeters, ?string $label): void
    {
        $existing = DB::table('office_location')->first();

        $data = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'label' => $label,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('office_location')->insert([...$data, 'created_at' => now()]);
        } else {
            DB::table('office_location')->where('id', $existing->id)->update($data);
        }
    }
}
