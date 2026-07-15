<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Annual', 'Sick', 'Casual', 'Unpaid', 'Maternity', 'Paternity']);

        return [
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'yearly_allocation_days' => fake()->numberBetween(5, 25),
            'carry_forward_enabled' => fake()->boolean(),
            'carry_forward_max_days' => fake()->numberBetween(0, 5),
            'description' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
