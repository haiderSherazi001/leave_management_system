<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->unique()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'check_in_at' => null,
            'check_out_at' => null,
            'status' => AttendanceStatus::Present->value,
            'notes' => null,
        ];
    }

    /**
     * No absent() state is provided deliberately — a row with
     * status='absent' never occurs in real data (nothing in the app ever
     * writes one), so a factory state for it would misrepresent how
     * absence is actually modeled: the total absence of a row.
     */
    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Late->value,
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::OnLeave->value,
        ]);
    }
}
