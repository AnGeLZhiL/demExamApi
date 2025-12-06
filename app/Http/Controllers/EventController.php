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
            $query->where('name', 'like', '%' . $request->search . '%');
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
        $event = Event::with(['status', 'modules'])->find($id);
        
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
        
        return $event->modules()->with(['type', 'status'])->get();
    }

    //получение всех пользователей, которые относятся к конкретному мероприятию, с фильтрацией
    public function getUsers($id, Request $request)
    {
        $event = Event::find($id);
        
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        $query = $event->users()->with(['role', 'group']);
        
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

    //получить учетные записи мероприятия с фильтрацией
    public function getEventAccounts($id, Request $request)
    {
        $event = Event::find($id);
        
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        $query = $event->eventAccounts()->with(['user.role', 'user.group']);
        
        // Фильтрация: исключить указанные роли
        if ($request->has('exclude_roles')) {
            $excludeRoles = explode(',', $request->exclude_roles);
            $query->whereHas('user.role', function($q) use ($excludeRoles) {
                $q->whereNotIn('name', $excludeRoles);
            });
        }
        
        // Фильтрация: только указанные роли  
        if ($request->has('roles')) {
            $roles = explode(',', $request->roles);
            $query->whereHas('user.role', function($q) use ($roles) {
                $q->whereIn('name', $roles);
            });
        }
        
        return $query->get();
    }
}
