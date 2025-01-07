<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'imagen' => 'nullable|string',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

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

        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'string|max:255',
            'imagen' => 'nullable|string',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

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
