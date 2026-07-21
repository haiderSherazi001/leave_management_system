<?php

declare(strict_types=1);

return [
    'office_latitude' => (float) env('OFFICE_LATITUDE', 31.411751),
    'office_longitude' => (float) env('OFFICE_LONGITUDE', 73.117245),
    'max_checkin_distance_meters' => (int) env('MAX_CHECKIN_DISTANCE_METERS', 100),
];
