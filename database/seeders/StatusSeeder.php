<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;
use App\Models\Context;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем или создаем контексты
        $contexts = [];
        foreach (['event', 'module', 'database', 'repository', 'server'] as $contextName) {
            $contexts[$contextName] = Context::firstOrCreate(['name' => $contextName]);
        }

        // Статусы для мероприятий (event)
        Status::create([
            'name' => 'Запланирован', 
            'context_id' => $contexts['event']->id
        ]);
        Status::create([
            'name' => 'Активен', 
            'context_id' => $contexts['event']->id
        ]);
        Status::create([
            'name' => 'Завершен', 
            'context_id' => $contexts['event']->id
        ]);
        Status::create([
            'name' => 'Отменен', 
            'context_id' => $contexts['event']->id
        ]);
        
        // Статусы для модулей (module)
        Status::create([
            'name' => 'Активен', 
            'context_id' => $contexts['module']->id
        ]);
        Status::create([
            'name' => 'Отключен', 
            'context_id' => $contexts['module']->id
        ]);
        
        // Статусы для БД (database)
        Status::create([
            'name' => 'Активна', 
            'context_id' => $contexts['database']->id
        ]);
        Status::create([
            'name' => 'Отключена', 
            'context_id' => $contexts['database']->id
        ]);
        
        // Статусы для репозиториев (repository)
        Status::create([
            'name' => 'Активен', 
            'context_id' => $contexts['repository']->id
        ]);
        Status::create([
            'name' => 'Отключен', 
            'context_id' => $contexts['repository']->id
        ]);
        
        $this->command->info('✅ Статусы созданы с контекстами');
    }
}
