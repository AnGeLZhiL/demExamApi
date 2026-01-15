<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'last_name' => $this->faker->lastName(),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->lastName(),
            'passport_data' => $this->faker->optional()->numerify('#### ######'),
            'birth_date' => $this->faker->optional()->date(),
            'group_id' => Group::factory(), // Создаст новую группу
        ];
    }

    /**
     * Пользователь без отчества
     */
    public function withoutMiddleName(): static
    {
        return $this->state(fn (array $attributes) => [
            'middle_name' => null,
        ]);
    }

    /**
     * Пользователь без группы
     */
    public function withoutGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => null,
        ]);
    }
}