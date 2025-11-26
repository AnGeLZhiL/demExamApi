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
    public function index()
    {
        return User::with(['role', 'group'])->get();
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
            'role_id' => $request->role_id,
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
        $user = User::with(['role', 'group'])->find($id);
        
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
            'last_name', 'first_name', 'middle_name', 'role_id', 'group_id'
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
        
        return $user->eventAccounts()->with(['event'])->get();
    }
}
