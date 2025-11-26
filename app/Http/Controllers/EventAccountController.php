<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventAccount;

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
        $account = EventAccount::create([
            'user_id' => $request->user_id,
            'event_id' => $request->event_id,
            'login' => $request->login,
            'password' => $request->password,
            'seat_number' => $request->seat_number
        ]);
        
        return response()->json($account, 201);
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
}
