<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'system_role' => false,
        ];
    }

    /**
     * Системная роль
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_role' => true,
        ]);
    }

    /**
     * Обычная роль
     */
    public function regular(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_role' => false,
        ]);
    }
}