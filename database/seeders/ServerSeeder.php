<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Server;
use App\Models\Type;
use Illuminate\Support\Facades\DB;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        $gogsTypeId = Type::where('name', 'Gogs')
            ->whereHas('context', function($query) {
                $query->where('name', 'server');
            })
            ->value('id');
        
        $postgresTypeId = Type::where('name', 'База данных PostgreSQL')
            ->whereHas('context', function($query) {
                $query->where('name', 'server');
            })
            ->value('id');
        
        $servers = [
            // Mock Gogs сервер для разработки
            [
                'name' => 'Mock Gogs Server',
                'type_id' => $gogsTypeId,
                'url' => 'http://localhost:3000',
                'port' => 3000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Локальная PostgreSQL база данных
            [
                'name' => 'Локальная БД PostgreSQL',
                'type_id' => $postgresTypeId,
                'url' => 'localhost',
                'port' => 5432,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Продакшен Gogs сервер (неактивный пока)
            [
                'name' => 'Продакшен Gogs',
                'type_id' => $gogsTypeId,
                'url' => 'https://git.example.com',
                'port' => 443,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($servers as $server) {
            Server::firstOrCreate(
                ['name' => $server['name']],
                $server
            );
        }
    }
}
