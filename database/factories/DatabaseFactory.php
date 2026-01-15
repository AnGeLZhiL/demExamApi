<?php

namespace Database\Factories;

use App\Models\Database;
use App\Models\Server;
use App\Models\Type;
use App\Models\EventAccount;
use App\Models\Module;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class DatabaseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Database::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'db_' . $this->faker->unique()->lexify('??????'), // Пример: db_abc123
            'username' => $this->faker->unique()->userName(), // Пример: user123
            'password' => bcrypt('password123'), // Хэшированный пароль
            'server_id' => Server::factory(), // Создаст новый сервер
            'type_id' => Type::factory(), // Создаст новый тип
            'event_account_id' => EventAccount::factory(), // Создаст нового участника
            'module_id' => Module::factory(), // Создаст новый модуль
            'status_id' => Status::factory(), // Создаст новый статус
            'is_active' => true,
            'is_public' => false,
            'has_demo_data' => false,
            'is_empty' => true,
            'metadata' => null, // Можно сделать json, но пока null
        ];
    }

    /**
     * Указать, что БД неактивна
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Указать, что БД публичная
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Указать, что БД с demo данными
     */
    public function withDemoData(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_demo_data' => true,
            'is_empty' => false,
        ]);
    }

    /**
     * Указать конкретный сервер
     */
    public function forServer(Server $server): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $server->id,
        ]);
    }

    /**
     * Указать конкретный модуль
     */
    public function forModule(Module $module): static
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
        ]);
    }
}