<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Module::create([
            'name' => 'Модуль проектирования',
            'event_id' => 1,
            'type_id' => 5,
            'status_id' => 1, // Запланирован
        ]);

        Module::create([
            'name' => 'Модуль работы с PostgreSQL', 
            'event_id' => 2, 
            'type_id' => 4, 
            'status_id' => 2, // Активен
        ]);
    }
}
