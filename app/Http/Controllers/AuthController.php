<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * функция авторезации 
     */
    public function login(Request $request)
    {
        // валидация
        $request->validate([
            'login' => 'required|string',    
            'password' => 'required|string', 
        ]);

        // поиск учетной записи по логину с загрузкой связей
        $eventAccount = EventAccount::with(['role'])
            ->where('login', $request->login)
            ->first();

        // проверка совпадения пароля
        if (!$eventAccount || !Hash::check($request->password, $eventAccount->password)) {
            throw ValidationException::withMessages([
                'login' => ['Неверные учетные данные.'],
            ]);
        }

        $user = $eventAccount->user;
    
        if (!$user) {
            throw ValidationException::withMessages([
                'login' => ['Пользователь не найден.'],
            ]);
        }

        // Создание токена
        $token = $user->createToken('auth-token')->plainTextToken;

        // Определяем тип учетки
        $isSystemAccount = is_null($eventAccount->event_id);
        
        // Формируем базовый ответ
        $response = [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'last_name' => $user->last_name,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'group_id' => $user->group_id
            ],
            'is_system_account' => $isSystemAccount,
        ];

        if ($isSystemAccount) {
            // СИСТЕМНАЯ УЧЕТКА (админ/наблюдатель)
            $response['message'] = 'Вход в систему как администратор';
        } else {
            // УЧЕТКА МЕРОПРИЯТИЯ
            $response['event_account'] = [
                'id' => $eventAccount->id,
                'event_id' => $eventAccount->event_id,
                'login' => $eventAccount->login,
                'seat_number' => $eventAccount->seat_number,
                'role_id' => $eventAccount->role_id,
                'role_name' => $eventAccount->role->name ?? null
            ];
            
            // Добавляем роль мероприятия
            if ($eventAccount->role) {
                $response['event_role'] = $eventAccount->role;
            }
            
            $response['message'] = 'Вход в мероприятие';
        }

        return response()->json($response);
    }

    /**
     * фцнкция выхода с учетной записи
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Успешный выход из системы'
        ]);
    }

    /**
     * функция получения авторизованного пользователя
     */
    public function user(Request $request)
    {
        $user = $request->user();
    
        // Находим последнюю учетную запись пользователя
        $eventAccount = EventAccount::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->with('role')
            ->first();
        
        $response = [
            'user' => [
                'id' => $user->id,
                'last_name' => $user->last_name,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'group_id' => $user->group_id
                
            ]
        ];
        
        if ($eventAccount) {
            $response['is_system_account'] = is_null($eventAccount->event_id);
            
            if (!is_null($eventAccount->event_id)) {
                $response['event_account'] = [
                    'id' => $eventAccount->id,
                    'event_id' => $eventAccount->event_id,
                    'role_id' => $eventAccount->role_id,
                    'role_name' => $eventAccount->role->name ?? null
                ];
                
                if ($eventAccount->role) {
                    $response['event_role'] = $eventAccount->role;
                }
            }
        }
        
        return response()->json($response);
    }
}