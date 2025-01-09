<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'sku' => 'string|unique:productos,sku|max:255', // SKU no obligatorio
            'tipo' => 'required|exists:tipo_productos,id',
            'marca' => 'required|exists:marcas,id',
            'control_stock' => 'required|boolean',
            'precio' => 'required|numeric',
            'permitir_venta_no_stock' => 'required|boolean',
            'nombre_variante' => 'nullable|string|max:255',
            'control_series' => 'required|boolean',
            'permitir_venta_decimales' => 'required|boolean',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
            'unique' => 'El campo :attribute debe ser único.',
            'exists' => 'El campo :attribute seleccionado no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'numeric' => 'El campo :attribute debe ser un número.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generar SKU automáticamente si no se proporciona
        $validated = $validator->validated();
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

        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'string|max:255',
            'sku' => 'string|unique:productos,sku|max:255', // SKU no obligatorio
            'tipo' => 'exists:tipo_productos,id',
            'marca' => 'exists:marcas,id',
            'control_stock' => 'boolean',
            'permitir_venta_no_stock' => 'boolean',
            'control_series' => 'boolean',
            'permitir_venta_decimales' => 'boolean',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
            'unique' => 'El campo :attribute debe ser único.',
            'exists' => 'El campo :attribute seleccionado no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'numeric' => 'El campo :attribute debe ser un número.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generar SKU automáticamente si no se proporciona
        $validated = $validator->validated();
        if (empty($validated['sku']) && isset($validated['nombre'])) {
            $validated['sku'] = $this->generateSku($validated['nombre']);
        }

        $producto->update($validated);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'producto' => $producto
        ]);
    }

    // Patch producto
    public function patch(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        // Validate request
        $validator = Validator::make($request->all(), [
            'nombre' => 'string|max:255',
            'sku' => 'string|unique:productos,sku|max:255', // SKU no obligatorio
            'tipo' => 'exists:tipo_productos,id',
            'marca' => 'exists:marcas,id',
            'control_stock' => 'boolean',
            'permitir_venta_no_stock' => 'boolean',
            'control_series' => 'boolean',
            'permitir_venta_decimales' => 'boolean',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no debe ser mayor que :max caracteres.',
            'unique' => 'El campo :attribute debe ser único.',
            'exists' => 'El campo :attribute seleccionado no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'numeric' => 'El campo :attribute debe ser un número.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generar SKU automáticamente si no se proporciona
        $validated = $validator->validated();
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
