<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource. Отобразите список ресурсов.
     * получить список всех ролей
     */
    public function index(Request $request)
    {
        $isSystem = $request->input('system_role');

        // Валидация: должно быть 0, 1, '0', '1' или null
        if ($isSystem !== null) {
            if (!in_array($isSystem, [0, 1, '0', '1'], true)) {
                return response()->json([
                    'error' => 'Параметр system_role должен быть 0 или 1'
                ], 400);
            }
            $isSystem = (bool)$isSystem; // Приводим к boolean
        }

        $query = Role::query();

        if ($isSystem !== null) {
            $query->where('system_role', $isSystem);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
