<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EventAccount;
use App\Models\User;
use App\Models\Event;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class EventAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        // 1. Главный эксперт Петрова (ID 12) - роль Главный эксперт (ID 4)
        EventAccount::create([
            'user_id' => 12,
            'event_id' => 1,
            'login' => 'petrova_lead_exam1',
            'password' => Hash::make('LeadExpertPass!'),
            'password_plain' => 'LeadExpertPass!',
            'seat_number' => null,
            'role_id' => 1
        ]);
        
        // 2. Эксперт Сидоров (ID 4) - роль Эксперт (ID 2)
        EventAccount::create([
            'user_id' => 4,
            'event_id' => 1,
            'login' => 'sidorov_expert_exam1',
            'password' => Hash::make('ExpertPass456'),
            'password_plain' => 'ExpertPass456',
            'seat_number' => null,
            'role_id' => 2
        ]);
        
        // 3. Технический эксперт Морозов (ID 14) - роль Технический эксперт (ID 5)
        EventAccount::create([
            'user_id' => 14,
            'event_id' => 1,
            'login' => 'morozov_tech_exam1',
            'password' => Hash::make('TechPass789'),
            'password_plain' => 'TechPass789',
            'seat_number' => null,
            'role_id' => 3
        ]);
        
        // 4-7. Участники группы 9901 (ID 5-8)
        $participantsExam1 = [
            ['user_id' => 5, 'last_name' => 'Козлова', 'login' => 'kozlova_exam1', 'seat' => 'A1', 'pass' => 'Student001'],
            ['user_id' => 6, 'last_name' => 'Белов', 'login' => 'belov_exam1', 'seat' => 'A2', 'pass' => 'Student002'],
            ['user_id' => 7, 'last_name' => 'Соколова', 'login' => 'sokolova_exam1', 'seat' => 'A3', 'pass' => 'Student003'],
            ['user_id' => 8, 'last_name' => 'Никитин', 'login' => 'nikitin_exam1', 'seat' => 'A4', 'pass' => 'Student004']
        ];
        
        foreach ($participantsExam1 as $p) {
            EventAccount::create([
                'user_id' => $p['user_id'],
                'event_id' => 1,
                'login' => $p['login'],
                'password' => Hash::make($p['pass']),
                'password_plain' => $p['pass'],
                'seat_number' => $p['seat'],
                'role_id' => 4
            ]);
            $this->command->info("   ✅ Участник {$p['last_name']} добавлен");
        }
      
        // 1. Главный эксперт Кузнецов (ID 13) - роль Главный эксперт (ID 4)
        EventAccount::create([
            'user_id' => 13,
            'event_id' => 2,
            'login' => 'kuznetsov_lead_exam2',
            'password' => Hash::make('LeadExpertPass2!'),
            'password_plain' => 'LeadExpertPass2!',
            'seat_number' => null,
            'role_id' => 1
        ]);
        
        // 2. Эксперт Фролова (ID 3) - роль Эксперт (ID 2)
        EventAccount::create([
            'user_id' => 3,
            'event_id' => 2,
            'login' => 'frolova_expert_exam2',
            'password' => Hash::make('ExpertPass789'),
            'password_plain' => 'ExpertPass789',
            'seat_number' => null,
            'role_id' => 2
        ]);
        
        // 3. Технический эксперт Захарова (ID 15) - роль Технический эксперт (ID 5)
        EventAccount::create([
            'user_id' => 15,
            'event_id' => 2,
            'login' => 'zakharova_tech_exam2',
            'password' => Hash::make('TechPass999'),
            'password_plain' => 'TechPass999',
            'seat_number' => null,
            'role_id' => 3
        ]);
        
        // 4-7. Участники группы 9903 (ID 9-11)
        $participantsExam2 = [
            ['user_id' => 9, 'last_name' => 'Волкова', 'login' => 'volkova_exam2', 'seat' => 'B1', 'pass' => 'Student101'],
            ['user_id' => 10, 'last_name' => 'Комаров', 'login' => 'komarov_exam2', 'seat' => 'B2', 'pass' => 'Student102'],
            ['user_id' => 11, 'last_name' => 'Орлова', 'login' => 'orlova_exam2', 'seat' => 'B3', 'pass' => 'Student103']
        ];
        
        foreach ($participantsExam2 as $p) {
            EventAccount::create([
                'user_id' => $p['user_id'],
                'event_id' => 2,
                'login' => $p['login'],
                'password' => Hash::make($p['pass']),
                'password_plain' => $p['pass'],
                'seat_number' => $p['seat'],
                'role_id' => 4
            ]);
            $this->command->info("   ✅ Участник {$p['last_name']} добавлен");
        }
        
        // Участник Волкова также в мероприятии 1 (пересдача)
        EventAccount::create([
            'user_id' => 9,
            'event_id' => 1,
            'login' => 'volkova_retake_exam1',
            'password' => Hash::make('RetakePass111'),
            'password_plain' => 'RetakePass111',
            'seat_number' => 'A5',
            'role_id' => 3
        ]);
        
        // Эксперт Фролова также в мероприятии 1 (тех эксперт)
        EventAccount::create([
            'user_id' => 3,
            'event_id' => 1,
            'login' => 'frolova_assist_exam1',
            'password' => Hash::make('AssistPass222'),
            'password_plain' => 'AssistPass222',
            'seat_number' => null,
            'role_id' => 3
        ]);

        // 1. Администратор Иванов - системная учетка
        EventAccount::create([
            'user_id' => 1, // Иванов
            'event_id' => null,
            'login' => 'ivanov_system',
            'password' => Hash::make('AdminPass123!'),
            'password_plain' => 'AdminPass123!',
            'seat_number' => null,
            'role_id' => 5
        ]);
        
        // 2. Администратор Смирнов - системная учетка
        EventAccount::create([
            'user_id' => 2, // Смирнов
            'event_id' => null,
            'login' => 'smirnov_system',
            'password' => Hash::make('AdminPass456!'),
            'password_plain' => 'AdminPass456!',
            'seat_number' => null,
            'role_id' => 5
        ]);
        
        // 3. Наблюдатель - системная учетка
        EventAccount::create([
            'user_id' => 16, 
            'event_id' => null, 
            'login' => 'observer_system',
            'password' => Hash::make('ObserverPass!'),
            'password_plain' => 'ObserverPass!',
            'seat_number' => null,
            'role_id' => 6
        ]);
    }   
}