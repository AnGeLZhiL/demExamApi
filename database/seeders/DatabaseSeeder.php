<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ContextSeeder::class,
            StatusSeeder::class,
            GroupSeeder::class,
            TypeSeeder::class,
            ServerSeeder::class,
            UserSeeder::class,
            EventSeeder::class,
            EventAccountSeeder::class,
            ModuleSeeder::class
        ]);
    }
}
