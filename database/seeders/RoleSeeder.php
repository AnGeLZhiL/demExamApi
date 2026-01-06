<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => 'Главный эксперт', 'system_role' => false]);
        Role::create(['name' => 'Эксперт', 'system_role' => false]);
        Role::create(['name' => 'Технический эксперт', 'system_role' => false]);
        Role::create(['name' => 'Участник', 'system_role' => false]);
        Role::create(['name' => 'Администратор', 'system_role' => true]);
        Role::create(['name' => 'Наблюдатель', 'system_role' => true]);
    }
}
