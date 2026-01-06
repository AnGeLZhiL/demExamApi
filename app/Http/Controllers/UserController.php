<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список всех пользователей
     */
    public function index(Request $request)
    {
        $query = User::with(['group']);
    
        // Поиск по ФИО
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            
            $query->where(function($q) use ($search) {
                $q->where('last_name', 'ilike', "%{$search}%")
                ->orWhere('first_name', 'ilike', "%{$search}%")
                ->orWhere('middle_name', 'ilike', "%{$search}%");
            });
        }
        
        // Исключить пользователей уже в мероприятии
        if ($request->has('not_in_event')) {
            $eventId = $request->not_in_event;
            $query->whereDoesntHave('eventAccounts', function($q) use ($eventId) {
                $q->where('event_id', $eventId);
            });
        }
        
        return $query->get();
    }

    /**
     * Store a newly created resource in storage. охраните вновь созданный ресурс в хранилище.
     * создание пользователя
     */
    public function store(Request $request)
    {
        $user = User::create([
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'group_id' => $request->group_id
        ]);
        
        return response()->json($user, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранного пользователя по указаному id
     */
    public function show(string $id)
    {
        $user = User::with([
            'group',
            'eventAccounts.role' => function ($query) {
                $query->where('roles.system_role', true);
            }
        ])->find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Извлекаем системную роль (если есть)
        $systemRole = $user->eventAccounts
            ->firstWhere(function ($eventAccount) {
                return $eventAccount->role && $eventAccount->role->system_role;
            })
            ->role ?? null;

        return response()->json([
            'user' => $user,
            'system_role' => $systemRole // теперь содержит всю роль (id, name и т.д.)
        ]);
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление пользователя 
     */
    public function update(Request $request, string $id)
    {
        \Log::info('UserController update called', [
            'user_id' => $id,
            'data' => $request->all()
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        // Только поля, которые есть в таблице users
        $allowedFields = [
            'last_name', 
            'first_name', 
            'middle_name', 
            'group_id'
        ];
        
        $updateData = $request->only($allowedFields);
        
        \Log::info('Updating user with data:', $updateData);
        
        $user->update($updateData);
        
        // Загружаем отношения
        $user->load(['group']);
        
        // ВАЖНО: метод update должен возвращать просто пользователя, а не структуру как show!
        return $user; // Просто $user, не обернутый в массив!
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление пользователя
     */
    public function destroy(string $id)
    {
        \Log::info('UserController destroy called', [
            'user_id' => $id,
            'current_user_id' => Auth::id()
        ]);
        
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }
        
        // 1. Проверка: нельзя удалить пользователя с id=1
        if ($user->id === 1) {
            return response()->json([
                'error' => 'Нельзя удалить системного администратора (пользователь ID=1)'
            ], 403);
        }
        
        // 2. Проверка: нельзя удалить себя
        $currentUserId = Auth::id();
        if ($user->id === $currentUserId) {
            return response()->json([
                'error' => 'Вы не можете удалить свою собственную учётную запись'
            ], 403);
        }
        
        // 3. Проверка: есть ли связанные записи (для информации)
        $eventAccountsCount = $user->eventAccounts()->count();
        \Log::info('У пользователя найдено event_accounts:', [
            'user_id' => $user->id,
            'event_accounts_count' => $eventAccountsCount
        ]);
        
        // 4. Удаление (с каскадом)
        try {
            $user->delete();
            
            \Log::info('Пользователь успешно удален', [
                'user_id' => $id,
                'name' => $user->last_name . ' ' . $user->first_name,
                'event_accounts_auto_deleted' => $eventAccountsCount
            ]);
            
            return response()->json([
                'message' => 'Пользователь успешно удален',
                'details' => [
                    'name' => $user->last_name . ' ' . $user->first_name,
                    'auto_deleted_event_accounts' => $eventAccountsCount
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Ошибка при удалении пользователя:', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Ошибка при удалении пользователя: ' . $e->getMessage()
            ], 500);
        }
    }

    // получение учетных записей пользователя учётные записи пользователя
    public function getEventAccounts($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        return $user->eventAccounts()->with(['event', 'role'])->get();
    }
}
