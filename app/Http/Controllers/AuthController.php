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

        // поиск учетной записи по логину
        $eventAccount = EventAccount::where('login', $request->login)->first();

        // проверка совпадения пароля
        if (!$eventAccount || !Hash::check($request->password, $eventAccount->password)) {
            throw ValidationException::withMessages([
                'login' => ['Неверные учетные данные.'],
            ]);
        }

        // результат поиска пользователя
        $user = User::find($eventAccount->user_id);

        //если пользователя с такими данными не нашли
        if (!$user) {
            throw ValidationException::withMessages([
                'login' => ['Пользователь не найден.'],
            ]);
        }

        // создание токена от этого пользователя
        $token = $user->createToken('event-auth')->plainTextToken;

        // возвращаемый ответ при успешной авторизации
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'last_name' => $user->last_name,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'role_id' => $user->role_id,
                'group_id' => $user->group_id
            ],
            'event_account' => [
                'id' => $eventAccount->id,
                'event_id' => $eventAccount->event_id, // 🎯 Система сама определяет мероприятие!
                'login' => $eventAccount->login,
                'seat_number' => $eventAccount->seat_number
            ]
        ]);
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
        return response()->json([
            'user' => $request->user()
        ]);
    }
}
