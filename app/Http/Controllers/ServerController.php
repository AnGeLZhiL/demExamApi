<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Server;

class ServerController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список серверов
     */
    public function index()
    {
        return Server::with(['type'])->get();
    }

    /**
     * Store a newly created resource in storage. Сохраните вновь созданный ресурс в хранилище.
     * создание сервера
     */
    public function store(Request $request)
    {
        $server = Server::create([
            'name' => $request->name,
            'type_id' => $request->type_id,
            'url' => $request->url,
            'port' => $request->port,
            'api_token' => $request->api_token
        ]);
        
        return response()->json($server, 201);
    }

    /**
     * Display the specified resource. Отобразите указанный ресурс.
     * отобразить выбранный сервер по указаному id
     */
    public function show(string $id)
    {
        $server = Server::with(['type'])->find($id);
        
        if (!$server) {
            return response()->json(['error' => 'Server not found'], 404);
        }
        
        return $server;
    }

    /**
     * Update the specified resource in storage. Обновите указанный ресурс в хранилище.
     * обновление сервера 
     */
    public function update(Request $request, string $id)
    {
        $server = Server::find($id);
        
        if (!$server) {
            return response()->json(['error' => 'Server not found'], 404);
        }
        
        $server->update($request->only([
            'name', 'type_id', 'url', 'port', 'api_token', 'is_active'
        ]));
        
        return $server;
    }

    /**
     * Remove the specified resource from storage. Удалите указанный ресурс из хранилища.
     * удаление сервера
     */
    public function destroy(string $id)
    {
        $server = Server::find($id);
        
        if (!$server) {
            return response()->json(['error' => 'Server not found'], 404);
        }
        
        $server->delete();
        return response()->noContent();
    }
}
