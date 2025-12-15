<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Status;
use App\Models\Context;

class StatusController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список всех статусов
     */
    public function index(Request $request)
    {
        $query = Status::with('context');
        
        // Фильтр по контексту (если передан)
        if ($request->has('context')) {
            $context = Context::where('name', $request->context)->first();
            if ($context) {
                $query->where('context_id', $context->id);
            }
        }
        
        // Фильтр по ID контекста (если передан напрямую)
        if ($request->has('context_id')) {
            $query->where('context_id', $request->context_id);
        }
        
        return $query->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание статуса
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'context_id' => 'nullable|exists:contexts,id'
        ]);
        
        $status = Status::create($validated);
        
        return response()->json($status, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранный статус по указаному id
     */
    public function show(string $id)
    {
        $status = Status::with('context')->find($id);
        
        if (!$status) {
            return response()->json(['error' => 'Status not found'], 404);
        }
        
        return $status;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление статуса 
     */ 
    public function update(Request $request, string $id)
    {
        $status = Status::find($id);
        
        if (!$status) {
            return response()->json(['error' => 'Status not found'], 404);
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'context_id' => 'nullable|exists:contexts,id'
        ]);
        
        $status->update($validated);
        
        return $status;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление статуса
     */
    public function destroy(string $id)
    {
        $status = Status::find($id);
        
        if (!$status) {
            return response()->json(['error' => 'Status not found'], 404);
        }
        
        $status->delete();
        return response()->noContent();
    }

    /**
     * Получить статусы по имени контекста
     */
    public function getByContext(string $contextName)
    {
        $context = Context::where('name', $contextName)->first();
        
        if (!$context) {
            return response()->json(['error' => 'Context not found'], 404);
        }
        
        return Status::where('context_id', $context->id)->get();
    }
}
