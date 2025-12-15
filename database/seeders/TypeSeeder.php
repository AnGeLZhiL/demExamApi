<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Type;
use App\Models\Context;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contexts = [
            'event' => Context::firstOrCreate(['name' => 'event']),
            'server' => Context::firstOrCreate(['name' => 'server']),
            'module' => Context::firstOrCreate(['name' => 'module']),
            'repository' => Context::firstOrCreate(['name' => 'repository']),
            'database' => Context::firstOrCreate(['name' => 'database']),
        ];


        // Серверы
        Type::create([
            'name' => 'Git-сервер', 
            'context_id' => $contexts['server']->id
        ]);
        Type::create([
            'name' => 'База данных PostgreSQL', 
            'context_id' => $contexts['server']->id
        ]);
        
        // Типы репозиториев
        Type::create([
            'name' => 'Тестовый', 
            'context_id' => $contexts['repository']->id
        ]);
        Type::create([
            'name' => 'Рабочий', 
            'context_id' => $contexts['repository']->id
        ]);
    }
}
