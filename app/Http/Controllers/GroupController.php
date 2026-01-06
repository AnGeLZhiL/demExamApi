<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use Illuminate\Http\Response;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     * Получить все группы
     */
    public function index(Request $request)
    {
        $query = Group::query();

        if ($request->has('number')) {
            $query->where('number', 'like', '%' . $request->input('number') . '%');
        }

        $groups = $query->get();
        return response()->json($groups, Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     * Создать новую группу
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:20|unique:groups'
        ]);

        $group = Group::create($validated);
        return response()->json($group, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     * Получить конкретную группу
     */
    public function show(string $id)
    {
        $group = Group::findOrFail($id);
        return response()->json($group, Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     * Обновить группу
     */
    public function update(Request $request, string $id)
    {
        $group = Group::findOrFail($id);

        $validated = $request->validate([
            'number' => 'required|string|max:20|unique:groups,number,' . $id
        ]);

        $group->update($validated);
        return response()->json($group, Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     * Удалить группу
     */
    public function destroy(string $id)
    {
        $group = Group::findOrFail($id);
        $group->delete();
        return response()->noContent();
    }
}
