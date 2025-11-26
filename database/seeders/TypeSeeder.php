<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Type;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Серверы
        Type::create(['name' => 'Git-сервер']);
        Type::create(['name' => 'База данных PostgreSQL']);
        Type::create(['name' => 'Веб-сервер']);
        
        // Типы модулей
        Type::create(['name' => 'Активный']);
        Type::create(['name' => 'Тестовый']);
        
        // Типы репозиториев
        Type::create(['name' => 'Тестовый репозиторий']);
        Type::create(['name' => 'Рабочий репозиторий']);
    }
}
