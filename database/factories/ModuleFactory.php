<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'event_id' => Event::factory(),
            'status_id' => Status::factory(),
        ];
    }

    /**
     * Модуль с конкретным именем
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Модуль для конкретного мероприятия
     */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
        ]);
    }
}