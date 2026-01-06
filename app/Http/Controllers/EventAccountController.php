<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventAccount;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            'event_id' => 'nullable|exists:events,id',
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
            'password' => $hashedPassword, // 🔴 поле должно называться 'password'
            'password_plain' => $rawPassword,
            'seat_number' => $validated['seat_number'] ?? null,
            'role_id' => $validated['role_id'] ?? 1
        ]);
        
        // Загружаем связи для ответа
        $account->load(['user', 'event', 'role']); 
        
        return response()->json([
            'message' => 'Учетная запись успешно создана',
            'data' => $account,
            'credentials' => [
                'login' => $login,
                'password' => $rawPassword,
                'event_name' => $event->name,
                'user_name' => $user->last_name . ' ' . $user->first_name
            ]
        ], 201);
    }

    /**
     * создание учетной записи системного пользователя
     */
    public function storeSystemAccount(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|in:5,6',
            'seat_number' => 'nullable|string|max:10',
        ]);

        // Проверка на дубликат (как у вас уже есть)
        $existingSystemAccount = EventAccount::where('user_id', $validated['user_id'])
            ->whereNull('event_id')
            ->where('role_id', $validated['role_id'])
            ->first();

        if ($existingSystemAccount) {
            return response()->json([
                'message' => 'У пользователя уже есть системная учётная запись для этой роли',
                'error' => 'system_account_exists_for_role',
                'data' => $existingSystemAccount->load(['user', 'role']),
            ], 409);
        }

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        // Генерируем чистый пароль (как у вас уже есть)
        $rawPassword = $this->generateRawPassword(); // ваша функция генерации
        $hashedPassword = Hash::make($rawPassword);

        $login = $this->generateSystemLogin($user);


        // Создаём учётную запись
        $account = EventAccount::create([
            'user_id' => $validated['user_id'],
            'event_id' => null,
            'login' => $login,
            'password' => $hashedPassword,
            'seat_number' => $validated['seat_number'] ?? null,
            'role_id' => $validated['role_id'],
        ]);

        $account->load(['user', 'role']);


        return response()->json([
            'message' => 'Системная учётная запись создана',
            'data' => $account,
            'credentials' => [
                'login' => $login,
                'raw_password' => $rawPassword, // <-- ВАЖНО: отдаём чистый пароль!
                'hashed_password' => $hashedPassword, // можно не отдавать, но для отладки полезно
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
        
        // Обновляем только разрешенные поля
        $allowedFields = ['login', 'seat_number', 'role_id'];
        
        $data = $request->only($allowedFields);
        
        // Если пришел новый пароль
        if ($request->has('password_plain') && !empty($request->password_plain)) {
            $data['password_plain'] = $request->password_plain;
            $data['password_hash'] = Hash::make($request->password_plain);
        }
        
        $account->update($data);
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
     * Обновление системной учётной записи
     */
    public function updateSystemAccount(Request $request, $userId)
    {
        \Log::info('=== UPDATE SYSTEM ACCOUNT ===');
        \Log::info('User ID: ' . $userId);
        \Log::info('Request data:', $request->all());

        try {
            // 1. Валидация
            $validated = $request->validate([
                'role_id' => 'required|in:5,6',
            ]);

            // 2. Находим пользователя
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'message' => 'Пользователь не найден',
                    'error' => 'user_not_found'
                ], 404);
            }

            // 3. Находим системные аккаунты пользователя
            $systemAccounts = EventAccount::where('user_id', $userId)
                ->whereNull('event_id')
                ->get();

            \Log::info('Найдено системных аккаунтов: ' . $systemAccounts->count());

            // 4. Если у пользователя нет системных аккаунтов
            if ($systemAccounts->isEmpty()) {
                \Log::info('У пользователя нет системных аккаунтов, создаем новый');
                
                // Проверяем, можно ли создать системный аккаунт
                $existingForRole = EventAccount::where('user_id', $userId)
                    ->whereNull('event_id')
                    ->where('role_id', $validated['role_id'])
                    ->first();
                    
                if ($existingForRole) {
                    return response()->json([
                        'message' => 'У пользователя уже есть системная учётная запись для этой роли',
                        'error' => 'system_account_exists_for_role',
                        'data' => $existingForRole->load(['user', 'role'])
                    ], 409);
                }
                
                // Создаем новый системный аккаунт
                $rawPassword = $this->generateRawPassword();
                $hashedPassword = Hash::make($rawPassword);
                $login = $this->generateSystemLogin($user);

                $newAccount = EventAccount::create([
                    'user_id' => $userId,
                    'event_id' => null,
                    'login' => $login,
                    'password' => $hashedPassword,
                    'seat_number' => null,
                    'role_id' => $validated['role_id'],
                ]);

                $newAccount->load(['user', 'role']);

                \Log::info('Создан новый системный аккаунт для пользователя');

                return response()->json([
                    'message' => 'Системная учётная запись создана',
                    'data' => $newAccount,
                    'credentials' => [
                        'login' => $login,
                        'raw_password' => $rawPassword
                    ]
                ], 201);
            }

            // 5. Если есть системные аккаунты
            // Проверяем, не меняется ли на ту же роль
            foreach ($systemAccounts as $account) {
                if ($account->role_id == $validated['role_id']) {
                    return response()->json([
                        'message' => 'Роль уже установлена',
                        'data' => $account->load(['user', 'role'])
                    ], 200);
                }
            }

            // 6. Берем первый системный аккаунт и обновляем его
            $account = $systemAccounts->first();
            $oldRoleId = $account->role_id;
            $account->role_id = $validated['role_id'];
            $account->save();

            $account->load(['user', 'role']);

            \Log::info('Роль обновлена: ' . $oldRoleId . ' -> ' . $validated['role_id']);

            return response()->json([
                'message' => 'Системная учётная запись обновлена',
                'data' => $account
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error in updateSystemAccount: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'message' => 'Внутренняя ошибка сервера',
                'error' => $e->getMessage()
            ], 500);
        }
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

    /**
     * Получить учетные записи мероприятия с паролями
     */
    public function getEventAccounts($eventId)
    {
        $accounts = EventAccount::where('event_id', $eventId)
            ->with(['user', 'role'])
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'user_id' => $account->user_id,
                    'login' => $account->login,
                    'password' => $account->password_plain, // 🔴 отправляем сырой пароль
                    'password_plain' => $account->password_plain,
                    'seat_number' => $account->seat_number,
                    'role' => $account->role,
                    'user' => $account->user,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at
                ];
            });
        
        return response()->json($accounts);
    }
    
    // 🔴 НОВЫЙ МЕТОД: Генерация "сырого" пароля
    private function generateRawPassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        // Гарантируем разные типы символов
        $password .= chr(rand(48, 57)); // цифра 0-9
        $password .= chr(rand(65, 90)); // заглавная буква A-Z
        $password .= chr(rand(97, 122)); // строчная буква a-z
        $password .= '!@#$%^&*'[rand(0, 7)]; // специальный символ
        
        // Добавляем случайные символы до длины 12
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return str_shuffle($password);
    }

    public function generatePassword($userId)
    {
        // Находим системную учётную запись пользователя
        $account = EventAccount::where('user_id', $userId)
            ->whereHas('role', function ($query) {
                $query->where('system_role', true);
            })
            ->first();

        if (!$account) {
            return response()->json(['error' => 'Системная учётная запись не найдена'], 404);
        }

        // Генерируем случайный пароль
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789#@#$%^&*';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }

        // Сохраняем зашифрованный пароль
        $account->password = Hash::make($password);
        $account->save();

        return response()->json([
            'password' => $password // Возвращаем **незашифрованный** пароль клиенту!
        ]);
    }


    private function generateSystemLogin(User $user): string
    {
        $lastName = strtolower($user->last_name);
        $hash = substr(md5($user->id), 0, 4); // первые 4 символа хеша
        return "{$lastName}_{$hash}";
    }

    //удалить системный аакаунт пользователя
    public function destroySystemAccounts(Request $request, $userId)
    {
        // Находим все системные аккаунты пользователя (event_id = null)
        $systemAccounts = EventAccount::where('user_id', $userId)
            ->whereNull('event_id') // это признак системного аккаунта
            ->get();

        if ($systemAccounts->isEmpty()) {
            return response()->json([
                'message' => 'У пользователя нет системных аккаунтов',
                'deleted_count' => 0
            ], 200);
        }

        // Удаляем их все
        foreach ($systemAccounts as $account) {
            $account->delete();
        }

        return response()->noContent(); // 204 No Content
    }
}
