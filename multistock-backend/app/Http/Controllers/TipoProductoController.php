<?php

namespace App\Http\Controllers;

use App\Models\TipoProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoProductoController extends Controller
{
    // Get all tipo productos
    public function index()
    {
        return response()->json(TipoProducto::all(), 200);
    }

    // Create a new tipo producto
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'producto' => 'required|string|max:255',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tipoProducto = TipoProducto::create($validator->validated());

        return response()->json([
            'message' => 'Tipo de producto creado exitosamente',
            'data' => $tipoProducto
        ], 201);
    }

    // Get a single tipo producto
    public function show($id)
    {
        $tipoProducto = TipoProducto::findOrFail($id);

        return response()->json($tipoProducto, 200);
    }

    // Update a tipo producto
    public function update(Request $request, $id)
    {
        $tipoProducto = TipoProducto::findOrFail($id);

        // Validate request
        $validator = Validator::make($request->all(), [
            'producto' => 'required|string|max:255',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tipoProducto->update($validator->validated());

        return response()->json([
            'message' => 'Tipo de producto actualizado exitosamente',
            'data' => $tipoProducto
        ], 200);
    }

    // Delete a tipo producto
    public function destroy($id)
    {
        $tipoProducto = TipoProducto::findOrFail($id);
        $tipoProducto->delete();

        return response()->json([
            'message' => 'Tipo de producto eliminado exitosamente'
        ], 204);
    }
}
