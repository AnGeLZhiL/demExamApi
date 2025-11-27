<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Repository;

class RepositoryController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список репозиториев
     */
    public function index()
    {
        return Repository::with(['server', 'type', 'eventAccount.user', 'module'])->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание репозитория
     */
    public function store(Request $request)
    {
        $repository = Repository::create([
            'name' => $request->name,
            'url' => $request->url,
            'server_id' => $request->server_id,
            'type_id' => $request->type_id,
            'event_account_id' => $request->event_account_id,
            'module_id' => $request->module_id,
            'is_active' => $request->is_active ?? true,
            'is_public' => $request->is_public ?? false
        ]);
        
        return response()->json($repository, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить репозиторий по указаному id
     */
    public function show(string $id)
    {
        $repository = Repository::with(['server', 'type', 'eventAccount.user', 'module'])->find($id);
        
        if (!$repository) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
        
        return $repository;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление репозитория 
     */
    public function update(Request $request, string $id)
    {
        $repository = Repository::find($id);
        
        if (!$repository) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
        
        $repository->update($request->only([
            'name', 'url', 'is_active', 'is_public'
        ]));
        
        return $repository;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление репозитория
     */
    public function destroy(string $id)
    {
        $repository = Repository::find($id);
        
        if (!$repository) {
            return response()->json(['error' => 'Repository not found'], 404);
        }
        
        $repository->delete();
        return response()->noContent();
    }
}
