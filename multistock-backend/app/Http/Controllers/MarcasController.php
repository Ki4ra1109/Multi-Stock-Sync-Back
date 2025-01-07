<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\Request;

class MarcasController extends Controller
{
    // Get all marcas
    public function index()
    {
        return Marca::all();
    }

    // Create marca
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'imagen' => 'nullable|string',
        ]);

        if (empty($validated['imagen'])) {
            $validated['imagen'] = 'https://example.com/default-image.png'; // Change this URL later
        }

        $marca = Marca::create($validated);

        return response()->json([
            'message' => 'Marca creada correctamente',
            'marca' => $marca
        ], 201);
    }

    // Show marca by id
    public function show($id)
    {
        $marca = Marca::findOrFail($id);

        return response()->json($marca);
    }

    // Update marca
    public function update(Request $request, $id)
    {
        $marca = Marca::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'string|max:255',
            'imagen' => 'nullable|string',
        ]);

        if (empty($validated['imagen'])) {
            $validated['imagen'] = 'https://example.com/default-image.png';
        }

        $marca->update($validated);

        return response()->json([
            'message' => 'Marca actualizada correctamente',
            'marca' => $marca
        ]);
    }

    // Delete marca
    public function destroy($id)
    {
        $marca = Marca::findOrFail($id);
        $marca->delete();

        return response()->json(['message' => 'Marca eliminada correctamente'], 200);
    }
}
