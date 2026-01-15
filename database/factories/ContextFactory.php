<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContextFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
        ];
    }

    /**
     * Контекст с конкретным именем
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Контекст "database" (для БД)
     */
    public function databaseContext(): static
    {
        return $this->withName('database');
    }

    /**
     * Контекст "server" (для серверов)
     */
    public function serverContext(): static
    {
        return $this->withName('server');
    }
}