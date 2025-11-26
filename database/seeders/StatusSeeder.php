<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Status::create(['name' => 'Запланирован']);
        Status::create(['name' => 'Активен']);
        Status::create(['name' => 'Завершён']);
        Status::create(['name' => 'Отменён']);
    }
}
