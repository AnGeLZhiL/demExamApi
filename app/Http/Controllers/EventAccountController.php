<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventAccount;
use App\Models\User;
use App\Models\Event;

class EventAccountController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список всех учетных записей
     */
    public function index()
    {
        return EventAccount::with(['user', 'event'])->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание учетной записи
     */
    public function store(Request $request)
    {
        // Валидация - только необходимые поля
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'event_id' => 'required|exists:events,id',
            'seat_number' => 'nullable|string|max:10'
            // login и password НЕ принимаем - генерируем сами
        ]);
        
        // Проверяем, не существует ли уже учетная запись
        $existingAccount = EventAccount::where('user_id', $validated['user_id'])
            ->where('event_id', $validated['event_id'])
            ->first();
            
        if ($existingAccount) {
            return response()->json([
                'message' => 'Пользователь уже добавлен в это мероприятие',
                'error' => 'user_already_exists'
            ], 409);
        }
        
        // Получаем пользователя и мероприятие
        $user = User::find($validated['user_id']);
        $event = Event::find($validated['event_id']);
        
        // Генерируем логин и пароль
        $login = $this->generateLogin($user, $event);
        $password = $this->generatePassword();
        
        // Создаем учетную запись
        $account = EventAccount::create([
            'user_id' => $validated['user_id'],
            'event_id' => $validated['event_id'],
            'login' => $login,
            'password' => $password,
            'seat_number' => $validated['seat_number'] ?? null
        ]);
        
        // Загружаем связи для ответа
        $account->load(['user', 'event']);
        
        return response()->json([
            'message' => 'Учетная запись успешно создана',
            'data' => $account,
            'generated_credentials' => [
                'login' => $login,
                'password' => $password
            ]
        ], 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранную учетную запись по указаному id
     */
    public function show(string $id)
    {
        $account = EventAccount::with(['user', 'event'])->find($id);
        
        if (!$account) {
            return response()->json(['error' => 'Event account not found'], 404);
        }
        
        return $account;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление учетной записи
     */
    public function update(Request $request, string $id)
    {
        $account = EventAccount::find($id);
        
        if (!$account) {
            return response()->json(['error' => 'Event account not found'], 404);
        }
        
        $account->update($request->only([
            'login', 'password', 'seat_number'
        ]));
        
        return $account;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление учетной записи
     */
    public function destroy(string $id)
    {
        $account = EventAccount::find($id);
        
        if (!$account) {
            return response()->json(['error' => 'Event account not found'], 404);
        }
        
        $account->delete();
        return response()->noContent();
    }

    /**
     * Генерация логина (пример: ivanov_ai_event1_xyz)
     */
    private function generateLogin(User $user, Event $event): string
    {
        // Берем первые 8 символов фамилии (латиницей)
        $lastName = transliterator_transliterate(
            'Russian-Latin/BGN', 
            $user->last_name
        );
        $lastName = strtolower(preg_replace('/[^a-z]/', '', $lastName));
        $lastName = substr($lastName, 0, 8);
        
        // Буква имени
        $firstNameLetter = transliterator_transliterate(
            'Russian-Latin/BGN',
            substr($user->first_name, 0, 1)
        );
        $firstNameLetter = strtolower($firstNameLetter);
        
        // Код мероприятия или ID
        $eventCode = $event->code ?? 'event' . $event->id;
        
        // Случайная часть
        $random = bin2hex(random_bytes(2)); // 4 случайных символа
        
        // Собираем логин
        $login = $lastName . '_' . $firstNameLetter . '_' . $eventCode . '_' . $random;
        
        // Проверяем уникальность
        $counter = 1;
        $originalLogin = $login;
        
        while (EventAccount::where('login', $login)->exists()) {
            $login = $originalLogin . $counter;
            $counter++;
            
            if ($counter > 5) {
                // Если не получается уникальный, добавляем timestamp
                $login = $originalLogin . '_' . time();
                break;
            }
        }
        
        return $login;
    }
    
    /**
     * Генерация пароля (10 символов: буквы + цифры)
     */
    private function generatePassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        
        // Гарантируем хотя бы одну цифру
        $password .= rand(0, 9);
        
        // Гарантируем хотя бы одну заглавную букву
        $password .= chr(rand(65, 90)); // A-Z
        
        // Остальные символы
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // Перемешиваем
        return str_shuffle($password);
    }
}
