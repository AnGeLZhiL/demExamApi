<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Администратор
        User::create([
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'middle_name' => 'Иванович',
            'role_id' => 1,
            'group_id' => null,
        ]);

        // Эксперт 
        User::create([
            'last_name' => 'Петрова',
            'first_name' => 'Мария',
            'middle_name' => 'Сергеевна',
            'role_id' => 2,
            'group_id' => null,
        ]);

        // Участники
        User::create([
            'last_name' => 'Сидоров',
            'first_name' => 'Алексей',
            'role_id' => 3,
            'group_id' => 1,
        ]);

        User::create([
            'last_name' => 'Козлова',
            'first_name' => 'Анна',
            'role_id' => 3,
            'group_id' => 2,
        ]);
    }
}
