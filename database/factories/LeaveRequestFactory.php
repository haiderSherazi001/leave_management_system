<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+2 months');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(0, 4).' days');

        return [
            'user_id' => User::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'is_half_day' => false,
            'total_days' => $startDate->diff($endDate)->days + 1,
            'reason' => fake()->sentence(),
            'status' => LeaveRequestStatus::PendingManager->value,
            'approver_id' => null,
            'hr_approver_id' => null,
            'decision_note' => null,
            'decided_at' => null,
        ];
    }

    public function pendingHr(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveRequestStatus::PendingHR->value,
            'approver_id' => User::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveRequestStatus::Approved->value,
            'approver_id' => User::factory(),
            'hr_approver_id' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveRequestStatus::Rejected->value,
            'approver_id' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
