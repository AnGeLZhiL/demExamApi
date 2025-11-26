<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EventAccount;
use App\Models\User;
use App\Models\Event;

class EventAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Учётная запись для пользователя 1 (Иванов) в мероприятии 1
        EventAccount::create([
            'user_id' => 1,
            'event_id' => 1,
            'login' => 'ivanov_exam1',
            'password' => 'password123',
            'seat_number' => 'A1'
        ]);

        // Учётная запись для пользователя 3 (Сидоров) в мероприятии 1
        EventAccount::create([
            'user_id' => 3, 
            'event_id' => 1,
            'login' => 'sidorov_exam1',
            'password' => 'password456',
            'seat_number' => 'A2'
        ]);

        // Учётная запись для пользователя 1 (Иванов) в мероприятии 2
        EventAccount::create([
            'user_id' => 1,
            'event_id' => 2,
            'login' => 'ivanov_exam2', 
            'password' => 'password789',
            'seat_number' => 'B1'
        ]);
    }
}
