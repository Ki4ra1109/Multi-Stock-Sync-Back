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
            'sku' => 'nullable|string|unique:productos,sku|max:255', // SKU opcional
            'tipo' => 'required|exists:tipo_productos,id',
            'marca' => 'required|exists:marcas,id',
            'control_stock' => 'required|boolean',
            'precio' => 'required|numeric',
            'permitir_venta_no_stock' => 'required|boolean',
            'nombre_variante' => 'nullable|string|max:255',
            'control_series' => 'required|boolean',
            'permitir_venta_decimales' => 'required|boolean',
        ]);

        // Generar SKU automáticamente si no se proporciona
        if (empty($validated['sku'])) {
            $validated['sku'] = $this->generateSku($validated['nombre']);
        }

        $producto = Producto::create($validated);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'producto' => $producto
        ], 201);
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
            'sku' => 'nullable|string|unique:productos,sku|max:255', // Validar SKU único
            'tipo' => 'exists:tipo_productos,id',
            'marca' => 'exists:marcas,id',
            'control_stock' => 'boolean',
            'permitir_venta_no_stock' => 'boolean',
            'control_series' => 'boolean',
            'permitir_venta_decimales' => 'boolean',
        ]);

        // Generar SKU automáticamente si no se proporciona
        if (empty($validated['sku']) && isset($validated['nombre'])) {
            $validated['sku'] = $this->generateSku($validated['nombre']);
        }

        $producto->update($validated);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'producto' => $producto
        ]);
    }

    // Delete producto
    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente'], 200);
    }

    // Método para generar SKU
    private function generateSku($nombre)
    {
        // Crear un SKU basado en el nombre y un número aleatorio
        $baseSku = strtoupper(substr($nombre, 0, 3)); // Tomar las primeras 3 letras del nombre
        $randomNumber = rand(1000, 9999); // Generar un número aleatorio de 4 dígitos
        return "{$baseSku}-{$randomNumber}";
    }
}
