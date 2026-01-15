<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'context_id' => Context::factory(),
        ];
    }

    /**
     * Тип с конкретным именем
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Тип "База данных PostgreSQL"
     */
    public function postgresDatabase(): static
    {
        return $this->withName('База данных PostgreSQL');
    }

    /**
     * Тип "Git-сервер"
     */
    public function gitServer(): static
    {
        return $this->withName('Git-сервер');
    }
}