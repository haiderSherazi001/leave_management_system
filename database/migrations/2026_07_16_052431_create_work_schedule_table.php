<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single company-wide schedule — one row, no per-department/per-employee
     * variation for now (matches the spec's "small company" MVP framing).
     */
    public function up(): void
    {
        Schema::create('work_schedule', function (Blueprint $table) {
            $table->id();
            $table->json('working_days');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule');
    }
};
