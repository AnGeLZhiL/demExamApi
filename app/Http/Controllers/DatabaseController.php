<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Database;

class DatabaseController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список БД
     */
    public function index()
    {
        return Database::with(['server', 'type', 'eventAccount.user', 'module'])->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание БД
     */
    public function store(Request $request)
    {
        $database = Database::create([
            'name' => $request->name,
            'server_id' => $request->server_id,
            'type_id' => $request->type_id,
            'event_account_id' => $request->event_account_id,
            'module_id' => $request->module_id,
            'is_active' => $request->is_active ?? true,
            'is_public' => $request->is_public ?? false
        ]);
        
        return response()->json($database, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить БД по указаному id
     */
    public function show(string $id)
    {
        $database = Database::with(['server', 'type', 'eventAccount.user', 'module'])->find($id);
        
        if (!$database) {
            return response()->json(['error' => 'Database not found'], 404);
        }
        
        return $database;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление БД
     */
    public function update(Request $request, string $id)
    {
        $database = Database::find($id);
        
        if (!$database) {
            return response()->json(['error' => 'Database not found'], 404);
        }
        
        $database->update($request->only([
            'name', 'is_active', 'is_public'
        ]));
        
        return $database;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление БД
     */
    public function destroy(string $id)
    {
        $database = Database::find($id);
        
        if (!$database) {
            return response()->json(['error' => 'Database not found'], 404);
        }
        
        $database->delete();
        return response()->noContent();
    }
}
