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
        // Получаем контексты
        $contexts = [
            'server' => Context::where('name', 'server')->first(),
            'repository' => Context::where('name', 'repository')->first(),
            'database' => Context::where('name', 'database')->first(),
            'event' => Context::where('name', 'event')->first(),
            'module' => Context::where('name', 'module')->first(),
        ];

        $types = [
            // Серверы
            [
                'name' => 'Gogs',
                'context_id' => $contexts['server']->id
            ],
            [
                'name' => 'GitLab',
                'context_id' => $contexts['server']->id
            ],
            [
                'name' => 'GitHub',
                'context_id' => $contexts['server']->id
            ],
            [
                'name' => 'База данных PostgreSQL',
                'context_id' => $contexts['server']->id
            ],
            
            // Репозитории
            [
                'name' => 'Тестовый',
                'context_id' => $contexts['repository']->id
            ],
            [
                'name' => 'Рабочий',
                'context_id' => $contexts['repository']->id
            ]
        ];

        foreach ($types as $type) {
            Type::firstOrCreate(
                ['name' => $type['name'], 'context_id' => $type['context_id']],
                $type
            );
        }
    }
}
