<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single company-wide office location — one row, same pattern as
     * work_schedule. No row yet means "not configured": GeoLocationService
     * falls back to the OFFICE_LATITUDE/OFFICE_LONGITUDE/
     * MAX_CHECKIN_DISTANCE_METERS .env defaults it already used before this
     * table existed, so nothing breaks for an install that hasn't visited
     * the new admin screen yet.
     */
    public function up(): void
    {
        Schema::create('office_location', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_location');
    }
};
