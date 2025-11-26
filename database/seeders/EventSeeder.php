<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Event;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         Event::create([
            'name' => 'Демонстрационный экзамен группы 9901',
            'date' => '2025-12-15 09:00:00',
            'status_id' => 1, // Запланирован
        ]);

        Event::create([
            'name' => 'Демонстрационный экзамен группы 9903', 
            'date' => '2025-11-23 09:00:00',
            'status_id' => 2, // Активен
        ]);
    }
}
