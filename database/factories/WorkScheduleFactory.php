<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'working_days' => [1, 2, 3, 4, 5],
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'grace_minutes' => 10,
        ];
    }
}
