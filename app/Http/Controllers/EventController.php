<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.Отобразите список ресурсов.
     * получить список всех мероприятий
     */
    public function index(Request $request)
    {
        $query = Event::with('status')
            ->orderBy('date', 'asc');
        
        // Поиск по названию
        if ($request->has('search') && $request->search) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }
        
        // Фильтрация по статусу
        if ($request->has('status_id') && $request->status_id) {
            $query->where('status_id', $request->status_id);
        }
        
        // Фильтрация по дате (от)
        if ($request->has('date_from') && $request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }
        
        // Фильтрация по дате (до)
        if ($request->has('date_to') && $request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }
        
        // Пока без пагинации - вернем все
        return $query->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->date,
                    'status' => [
                        'id' => $event->status->id,
                        'name' => $event->status->name
                    ]
                ];
            });
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание мероприятия
     */
    public function store(Request $request)
    {
        // 🔴 ПРОСТАЯ ПРОВЕРКА - ДОСТАТОЧНО ДЛЯ ДЕМО
        if (!$request->name || !$request->date || !$request->status_id) {
            return response()->json(['error' => 'Заполните все поля'], 422);
        }
        
        // 🔴 TIMESTAMP ПОДХОД - РЕШАЕТ ПРОБЛЕМУ ЧАСОВОГО ПОЯСА
        try {
            $date = Carbon::createFromTimestampMs($request->date)
                        ->setTimezone('Europe/Moscow');
            
            $event = Event::create([
                'name' => $request->name,
                'date' => $date,
                'status_id' => $request->status_id
            ]);
            
            return response()->json($event, 201);
            
        } catch (\Exception $e) {
            \Log::error('Ошибка создания мероприятия: ' . $e->getMessage());
            return response()->json(['error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранное мероприятие по указаному id
     */
    public function show(string $id)
    {
        $event = Event::with([
        'status', // статус мероприятия
        'modules' => function($query) {
                // Модули с их типами и статусами
                $query->with([
                    // 'type' => function($q) {
                    //     $q->with('context'); // тип с контекстом
                    // },
                    'status' => function($q) {
                        $q->with('context'); // статус с контекстом
                    }
                ]);
            }
        ])->find($id);
        
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        return $event;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление мероприятия 
     */
    public function update(Request $request, string $id)
    {
        $event = Event::find($id);
        
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        $event->update($request->only([
            'name', 'date', 'status_id'
        ]));
        
        return $event;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление мероприятия
     */
    public function destroy(string $id)
    {
        $event = Event::find($id);
        
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        $event->delete();
        return response()->noContent();
    }

    //получение всех модулей, которые относятся к конкретному мероприятию
    public function getModules($id)
    {
        $event = Event::find($id);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        // Модули с типами и статусами
        return $event->modules()
            ->with([
                // 'type' => function($query) {
                //     $query->with('context'); // тип с контекстом
                // },
                'status' => function($query) {
                    $query->with('context'); // статус с контекстом
                }
            ])
            ->get();
    }

    //получение всех пользователей, которые относятся к конкретному мероприятию, с фильтрацией
    public function getUsers($id, Request $request)
    {
        $event = Event::find($id);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        // Получаем учетные записи мероприятия с пользователями и их ролями
        $query = $event->eventAccounts()->with(['user.group', 'role']);
        
        // ФИЛЬТРАЦИЯ ПО РОЛИ (через event_accounts.role)
        if ($request->has('exclude_roles')) {
            $excludeRoles = explode(',', $request->exclude_roles);
            $query->whereHas('role', function($q) use ($excludeRoles) {
                $q->whereNotIn('name', $excludeRoles);
            });
        }
        
        if ($request->has('roles')) {
            $roles = explode(',', $request->roles);
            $query->whereHas('role', function($q) use ($roles) {
                $q->whereIn('name', $roles);
            });
        }
        
        // Получаем данные
        $eventAccounts = $query->get();
        
        // Преобразуем: каждая учетная запись → пользователь с информацией о роли в мероприятии
        $usersWithEventData = $eventAccounts->map(function ($account) {
            $user = $account->user;
            
            return [
                // Данные пользователя
                'id' => $user->id,
                'last_name' => $user->last_name,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'birth_date' => $user->birth_date,
                'passport_data' => $user->passport_data,
                'group' => $user->group,
                
                // Данные из учетной записи мероприятия
                'event_account_id' => $account->id,
                'login' => $account->login,
                'seat_number' => $account->seat_number,
                
                // Роль пользователя в ЭТОМ мероприятии
                'role_in_event' => $account->role,
                'role_id' => $account->role_id
            ];
        });
        
        return $usersWithEventData;
    }

    //получить учетные записи мероприятия с фильтрацией
    public function getEventAccounts($id, Request $request)
    {
        $event = Event::find($id);
    
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        // 🔴 ИЗМЕНИТЬ: with(['user.group', 'role'])
        $query = $event->eventAccounts()->with(['user.group', 'role']);
        
        // 🔴 ИЗМЕНИТЬ: whereHas('role', ...) вместо whereHas('user.role', ...)
        if ($request->has('exclude_roles')) {
            $excludeRoles = explode(',', $request->exclude_roles);
            $query->whereHas('role', function($q) use ($excludeRoles) {
                $q->whereNotIn('name', $excludeRoles);
            });
        }
        
        if ($request->has('roles')) {
            $roles = explode(',', $request->roles);
            $query->whereHas('role', function($q) use ($roles) {
                $q->whereIn('name', $roles);
            });
        }
        
        return $query->get();
    }
}
