<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Type;
use App\Models\Context;

class TypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Type::query();
        
        // Фильтр по контексту (если передан context_id)
        if ($request->has('context_id')) {
            $query->where('context_id', $request->context_id);
        }
        
        // Фильтр по имени контекста (если передан context)
        if ($request->has('context')) {
            $context = Context::where('name', $request->context)->first();
            if ($context) {
                $query->where('context_id', $context->id);
            }
        }
        
        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:types',
            'context_id' => 'nullable|exists:contexts,id'
        ]);
        
        $type = Type::create($validated);
        
        return response()->json($type, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $type = Type::find($id);
        
        if (!$type) {
            return response()->json(['error' => 'Type not found'], 404);
        }
        
        return $type;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $type = Type::find($id);
        
        if (!$type) {
            return response()->json(['error' => 'Type not found'], 404);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:types,name,' . $id,
            'context_id' => 'nullable|exists:contexts,id'
        ]);
        
        $type->update($validated);
        
        return $type;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $type = Type::find($id);
        
        if (!$type) {
            return response()->json(['error' => 'Type not found'], 404);
        }
        
        $type->delete();
        return response()->noContent();
    }
    
    /**
     * Получить типы по имени контекста (дополнительный метод)
     */
    public function getByContext(string $contextName)
    {
        $context = Context::where('name', $contextName)->first();
        
        if (!$context) {
            return response()->json(['error' => 'Context not found'], 404);
        }
        
        return Type::where('context_id', $context->id)->get();
    }
}