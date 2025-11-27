<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;

class FileController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список файлов
     */
    public function index()
    {
        return File::with(['module', 'eventAccount.user'])->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание файла
     */
    public function store(Request $request)
    {
        $file = File::create([
            'name' => $request->name,
            'path' => $request->path,
            'size' => $request->size,
            'mime_type' => $request->mime_type,
            'module_id' => $request->module_id,
            'event_account_id' => $request->event_account_id,
            'is_public' => $request->is_public ?? true
        ]);
        
        return response()->json($file, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить файл по указаному id
     */
    public function show(string $id)
    {
        $file = File::with(['module', 'eventAccount.user'])->find($id);
        
        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }
        
        return $file;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление файла
     */
    public function update(Request $request, string $id)
    {
        $file = File::find($id);
        
        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }
        
        $file->update($request->only([
            'name', 'is_public'
        ]));
        
        return $file;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление Файла
     */
    public function destroy(string $id)
    {
        $file = File::find($id);
        
        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }
        
        $file->delete();
        return response()->noContent();
    }
}
