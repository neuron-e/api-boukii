<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseInterval>
 */
class CourseIntervalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');
        $endDate = (clone $startDate)->modify('+2 weeks');

        return [
            'course_id' => \App\Models\Course::factory(),
            'name' => $this->faker->randomElement(['Semana 1', 'Semana 2', 'Semana 3', 'KW ' . $this->faker->numberBetween(1, 52)]),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'display_order' => $this->faker->numberBetween(0, 10),
            'config_mode' => $this->faker->randomElement(['inherit', 'custom']),
            'date_generation_method' => $this->faker->randomElement(['consecutive', 'weekly', 'manual', 'first_day']),
            'consecutive_days_count' => $this->faker->numberBetween(3, 7),
            'weekly_pattern' => null,
            'booking_mode' => $this->faker->randomElement(['flexible', 'package']),
        ];
    }
}
