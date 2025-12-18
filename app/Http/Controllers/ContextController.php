<?php

namespace App\Http\Controllers;

use App\Models\Context;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Context::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:contexts|max:255',
        ]);

        $context = Context::create([
            'name' => $request->name,
        ]);

        return response()->json($context, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $context = Context::find($id);
        
        if (!$context) {
            return response()->json(['error' => 'Context not found'], 404);
        }
        
        return $context;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $context = Context::find($id);
        
        if (!$context) {
            return response()->json(['error' => 'Context not found'], 404);
        }
        
        $request->validate([
            'name' => 'required|string|unique:contexts,name,' . $id . '|max:255',
        ]);
        
        $context->update([
            'name' => $request->name,
        ]);
        
        return $context;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $context = Context::find($id);
        
        if (!$context) {
            return response()->json(['error' => 'Context not found'], 404);
        }
        
        $context->delete();
        return response()->noContent();
    }
}