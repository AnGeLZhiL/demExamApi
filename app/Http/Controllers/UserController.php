<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

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
        $user = User::with(['group'])->find($id);
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        return $user;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление пользователя 
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
    
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $user->update($request->only([
            'last_name', 'first_name', 'middle_name', 'group_id'
        ]));
        
        return $user;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление пользователя
     */
    public function destroy(string $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $user->delete();
        return response()->noContent();
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
