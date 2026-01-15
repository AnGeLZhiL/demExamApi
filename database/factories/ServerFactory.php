<?php

namespace Database\Factories;

use App\Models\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Server',
            'type_id' => Type::factory(),
            'url' => $this->faker->domainName(),
            'port' => $this->faker->optional()->numberBetween(1, 65535),
            'is_active' => true,
        ];
    }

    /**
     * Неактивный сервер
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Сервер с конкретным портом
     */
    public function withPort(int $port): static
    {
        return $this->state(fn (array $attributes) => [
            'port' => $port,
        ]);
    }
}