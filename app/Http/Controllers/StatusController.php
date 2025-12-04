<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Status;

class StatusController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список всех статусов
     */
    public function index()
    {
        return Status::select('id', 'name')->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание статуса
     */
    public function store(Request $request)
    {
        $status = Status::create([
            'name' => $request->name
        ]);
        
        return response()->json($status, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранный статус по указаному id
     */
    public function show(string $id)
    {
        $status = Status::find($id);
        
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
        
        $status->update($request->only([
            'name'
        ]));
        
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
}
