<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Module;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список всех модулей
     */
    public function index()
    {
        return Module::with(['event', /*'type',*/ 'status'])->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание модуля
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'event_id' => 'required|exists:events,id',
            // 'type_id' => 'required|exists:types,id',
            'status_id' => 'required|exists:statuses,id'
        ]);
    
        $module = Module::create($validated);
        return response()->json($module, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранный модуль по указаному id
     */
    public function show(string $id)
    {
        $module = Module::with(['event', /*'type',*/ 'status'])->find($id);
        
        if (!$module) {
            return response()->json(['error' => 'Module not found'], 404);
        }
        
        return $module;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление модуля 
     */
    public function update(Request $request, string $id)
    {
        $module = Module::find($id);
        
        if (!$module) {
            return response()->json(['error' => 'Module not found'], 404);
        }
        
        $module->update($request->only([
            'name', 'event_id', /*'type_id',*/ 'status_id'
        ]));
        
        return $module;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление модуля
     */
    public function destroy(string $id)
    {
        $module = Module::find($id);
        
        if (!$module) {
            return response()->json(['error' => 'Module not found'], 404);
        }
        
        $module->delete();
        return response()->noContent();
    }
}
