<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventAccount;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Hash;

class EventAccountController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список всех учетных записей
     */
    public function index()
    {
        return EventAccount::with(['user', 'event', 'role'])->get();
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
            'seat_number' => 'nullable|string|max:10',
            'role_id' => 'nullable|exists:roles,id' // ← ДОБАВИТЬ ЭТУ СТРОКУ
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
        $rawPassword = $this->generateRawPassword(); // ← НОВЫЙ МЕТОД ДЛЯ "СЫРОГО" ПАРОЛЯ
        $hashedPassword = Hash::make($rawPassword); // ← ХЭШИРУЕМ
        
        // Создаем учетную запись
         $account = EventAccount::create([
            'user_id' => $validated['user_id'],
            'event_id' => $validated['event_id'],
            'login' => $login,
            'password' => $hashedPassword, // ← СОХРАНЯЕМ ХЭШ
            'seat_number' => $validated['seat_number'] ?? null,
            'role_id' => $validated['role_id'] ?? 1
        ]);
        
        // Загружаем связи для ответа
        $account->load(['user', 'event', 'role']); 
        
        return response()->json([
            'message' => 'Учетная запись успешно создана',
            'data' => $account,
            'credentials' => [  // ← ВОЗВРАЩАЕМ КРЕДЫ ДЛЯ ВЫДАЧИ
                'login' => $login,
                'password' => $rawPassword, // ← ОРИГИНАЛЬНЫЙ пароль
                'event_name' => $event->name,
                'user_name' => $user->last_name . ' ' . $user->first_name
            ]
        ], 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранную учетную запись по указаному id
     */
    public function show(string $id)
    {
        $account = EventAccount::with(['user', 'event', 'role'])->find($id);
        
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
        
        // Разрешаем обновлять login, password, seat_number, role_id
        $account->update($request->only([
            'login', 'password', 'seat_number', 'role_id' // ← ДОБАВИТЬ 'role_id'
        ]));

        $account->load(['user', 'event', 'role']);
        
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
        // Простая транслитерация русских букв
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Shch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
        ];
        
        // Транслитерируем фамилию
        $lastName = strtr(mb_strtolower($user->last_name, 'UTF-8'), $translitMap);
        $lastName = preg_replace('/[^a-z]/', '', $lastName);
        $lastName = substr($lastName, 0, 8);
        
        // Первая буква имени
        $firstName = mb_strtolower($user->first_name, 'UTF-8');
        $firstNameLetter = strtr(mb_substr($firstName, 0, 1, 'UTF-8'), $translitMap);
        
        // Код мероприятия
        $eventCode = $event->code ?? 'event' . $event->id;
        
        // Случайная часть
        $random = substr(md5(uniqid()), 0, 4);
        
        // Собираем логин
        $login = $lastName . '_' . $firstNameLetter . '_' . $eventCode . '_' . $random;
        
        // Проверяем уникальность
        $counter = 1;
        $originalLogin = $login;
        
        while (EventAccount::where('login', $login)->exists()) {
            $login = $originalLogin . $counter;
            $counter++;
            
            if ($counter > 5) {
                $login = $originalLogin . '_' . time();
                break;
            }
        }
        
        return $login;
    }
    
    // 🔴 НОВЫЙ МЕТОД: Генерация "сырого" пароля
    private function generateRawPassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        
        $password .= rand(0, 9); // цифра
        $password .= chr(rand(65, 90)); // заглавная буква
        
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return str_shuffle($password);
    }

    // 🔴 СТАРЫЙ МЕТОД: Теперь только для хэширования
    private function generatePassword(): string
    {
        $rawPassword = $this->generateRawPassword();
        return Hash::make($rawPassword);
    }
}
