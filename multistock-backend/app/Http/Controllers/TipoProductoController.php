<?php

namespace App\Http\Controllers;

use App\Models\TipoProducto;
use Illuminate\Http\Request;

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
        $request->validate([
            'producto' => 'required|string|max:255',
        ]);

        $tipoProducto = TipoProducto::create($request->all());

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
        $request->validate([
            'producto' => 'required|string|max:255',
        ]);

        $tipoProducto = TipoProducto::findOrFail($id);
        $tipoProducto->update($request->all());

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
