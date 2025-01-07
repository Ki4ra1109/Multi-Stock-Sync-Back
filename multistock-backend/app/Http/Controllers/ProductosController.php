<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

class ProductosController extends Controller
{
    // Get all productos
    public function index()
    {
        return Producto::with(['tipoProducto', 'marca', 'stock'])->get();
    }

    // Create producto
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|exists:tipo_productos,id',
            'marca' => 'required|exists:marcas,id',
            'control_stock' => 'required|boolean',
            'permitir_venta_no_stock' => 'required|boolean',
            'control_series' => 'required|boolean',
            'permitir_venta_decimales' => 'required|boolean',
        ]);

        $producto = Producto::create($validated);

        return response()->json($producto, 201);
    }

    // Show producto by id
    public function show($id)
    {
        $producto = Producto::with(['tipoProducto', 'marca', 'stock'])->findOrFail($id);

        return response()->json($producto);
    }

    // Update producto
    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'string|max:255',
            'tipo' => 'exists:tipo_productos,id',
            'marca' => 'exists:marcas,id',
            'control_stock' => 'boolean',
            'permitir_venta_no_stock' => 'boolean',
            'control_series' => 'boolean',
            'permitir_venta_decimales' => 'boolean',
        ]);

        $producto->update($validated);

        return response()->json($producto);
    }

    // Delete producto
    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente'], 204);
    }
}
