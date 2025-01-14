<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrandsController extends Controller
{
    // Get all brands
    public function index()
    {
        return Brand::all();
    }

    // Create marca
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|string',
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

        $marca = Brand::create($validated);

        return response()->json([
            'message' => 'Marca creada correctamente',
            'marca' => $marca
        ], 201);
    }

    // Show marca by id
    public function show($id)
    {
        $brand = Brand::findOrFail($id);

        return response()->json($brand);
    }

    // Update marca
    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);

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

        $brand->update($validated);

        return response()->json([
            'message' => 'Marca actualizada correctamente',
            'brand' => $brand
        ]);
    }

    // Patch marca
    public function patch(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);

        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'imagen' => 'nullable|string',
        ], [
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        if (array_key_exists('imagen', $validated) && empty($validated['imagen'])) {
            $validated['imagen'] = 'https://example.com/default-image.png';
        }

        $brand->update($validated);

        return response()->json([
            'message' => 'Marca actualizada parcialmente correctamente',
            'brand' => $brand
        ]);
    }

    // Delete marca
    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();

        return response()->json(['message' => 'Marca eliminada correctamente'], 200);
    }
}
