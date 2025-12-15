<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contexts = [
            ['name' => 'event'],
            ['name' => 'module'],
            ['name' => 'database'],
            ['name' => 'repository'],
            ['name' => 'server'],
        ];

        DB::table('contexts')->insert($contexts);
    }
}
