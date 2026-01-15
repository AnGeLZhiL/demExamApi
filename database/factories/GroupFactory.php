<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->bothify('Группа-###'),
        ];
    }

    /**
     * Группа с конкретным номером
     */
    public function withNumber(string $number): static
    {
        return $this->state(fn (array $attributes) => [
            'number' => $number,
        ]);
    }
}