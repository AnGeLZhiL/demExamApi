<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventAccountFactory extends Factory
{
    public function definition(): array
    {
        $plainPassword = 'password123'; // Пароль в открытом виде
        
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'role_id' => Role::factory(),
            'login' => $this->faker->unique()->userName(),
            'password' => bcrypt($plainPassword), // Хэшированный пароль
            'password_plain' => $plainPassword, // Пароль в открытом виде
            'seat_number' => $this->faker->optional()->numerify('###'),
        ];
    }

    /**
     * Учетная запись с конкретным паролем
     */
    public function withPassword(string $plainPassword): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => bcrypt($plainPassword),
            'password_plain' => $plainPassword,
        ]);
    }

    /**
     * Учетная запись без места
     */
    public function withoutSeat(): static
    {
        return $this->state(fn (array $attributes) => [
            'seat_number' => null,
        ]);
    }

    /**
     * Учетная запись с конкретным местом
     */
    public function withSeat(string $seat): static
    {
        return $this->state(fn (array $attributes) => [
            'seat_number' => $seat,
        ]);
    }
}