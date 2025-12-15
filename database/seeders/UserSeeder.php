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
            'group_id' => null,
            'system_role_id' => 1
        ]);
        User::create([
            'last_name' => 'Смирнов',
            'first_name' => 'Александр',
            'middle_name' => 'Петрович',
            'group_id' => null,
            'system_role_id' => 1
        ]);

        // Эксперт 
        User::create([
            'last_name' => 'Фролова',
            'first_name' => 'Екатерина',
            'middle_name' => 'Игоревна',
            'group_id' => null,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Сидоров',
            'first_name' => 'Алексей',
            'middle_name' => 'Викторович',
            'group_id' => null,
            'system_role_id' => null
        ]);

        // Участники
        User::create([
            'last_name' => 'Козлова',
            'first_name' => 'Анна',
            'group_id' => 1,
            'system_role_id' => null
        ]);

        User::create([
            'last_name' => 'Белов',
            'first_name' => 'Артем',
            'middle_name' => 'Андреевич',
            'group_id' => 1,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Соколова',
            'first_name' => 'Дарья',
            'middle_name' => 'Михайловна',
            'group_id' => 1,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Никитин',
            'first_name' => 'Илья',
            'middle_name' => 'Владимирович',
            'group_id' => 1,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Волкова',
            'first_name' => 'Елена',
            'middle_name' => 'Дмитриевна',
            'group_id' => 2,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Комаров',
            'first_name' => 'Андрей',
            'middle_name' => 'Сергеевич',
            'group_id' => 2,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Орлова',
            'first_name' => 'Виктория',
            'middle_name' => 'Александровна',
            'group_id' => 2,
            'system_role_id' => null
        ]);

        // Главные эксперты
        User::create([
            'last_name' => 'Петрова',
            'first_name' => 'Мария',
            'middle_name' => 'Сергеевна',
            'group_id' => null,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Кузнецов',
            'first_name' => 'Дмитрий',
            'middle_name' => 'Алексеевич',
            'group_id' => null,
            'system_role_id' => null
        ]);

        //Технические эксперты
        User::create([
            'last_name' => 'Морозов',
            'first_name' => 'Сергей',
            'middle_name' => 'Анатольевич',
            'group_id' => null,
            'system_role_id' => null
        ]);
        User::create([
            'last_name' => 'Захарова',
            'first_name' => 'Анна',
            'middle_name' => 'Владимировна',
            'group_id' => null,
            'system_role_id' => null
        ]);

        //Наблюдатель
        User::create([
            'last_name' => 'Наблюдателев',
            'first_name' => 'Олег',
            'middle_name' => 'Владимирович',
            'group_id' => null,
            'system_role_id' => 2
        ]);
    }
}
