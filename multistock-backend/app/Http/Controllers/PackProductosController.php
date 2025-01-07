<?php

namespace App\Http\Controllers;

use App\Models\PackProducto;
use App\Models\PackComposicion;
use App\Models\Producto;
use Illuminate\Http\Request;

class PackProductosController extends Controller
{
    // Get all packs with their compositions
    public function index()
    {
        $packs = PackProducto::with('composicion.producto')->get();

        return response()->json($packs, 200);
    }

    // Create a new pack
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku_pack' => 'required|integer|unique:pack_productos,sku_pack',
            'nombre' => 'required|string|max:255',
            'composicion' => 'required|array',
            'composicion.*.sku_producto' => 'required|exists:productos,id',
            'composicion.*.cantidad_pack' => 'required|integer|min:1',
        ]);

        // Crear el pack
        $pack = PackProducto::create([
            'sku_pack' => $validated['sku_pack'],
            'nombre' => $validated['nombre'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Guardar la composición del pack
        foreach ($validated['composicion'] as $componente) {
            PackComposicion::create([
                'sku_pack' => $pack->id,
                'sku_producto' => $componente['sku_producto'],
                'cantidad_pack' => $componente['cantidad_pack'],
            ]);
        }

        return response()->json([
            'message' => 'Pack creado correctamente',
            'pack' => $pack->load('composicion.producto'),
        ], 201);
    }

    // Get a single pack by id
    public function show($id)
    {
        $pack = PackProducto::with('composicion.producto')->findOrFail($id);

        return response()->json($pack, 200);
    }

    // Update a pack
    public function update(Request $request, $id)
    {
        $pack = PackProducto::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'composicion' => 'sometimes|array',
            'composicion.*.sku_producto' => 'required_with:composicion|exists:productos,id',
            'composicion.*.cantidad_pack' => 'required_with:composicion|integer|min:1',
        ]);

        // Actualizar el pack
        $pack->update([
            'nombre' => $validated['nombre'] ?? $pack->nombre,
            'updated_at' => now(),
        ]);

        // Si se envía una nueva composición, actualizarla
        if (isset($validated['composicion'])) {
            // Eliminar la composición existente
            PackComposicion::where('sku_pack', $pack->id)->delete();

            // Crear la nueva composición
            foreach ($validated['composicion'] as $componente) {
                PackComposicion::create([
                    'sku_pack' => $pack->id,
                    'sku_producto' => $componente['sku_producto'],
                    'cantidad_pack' => $componente['cantidad_pack'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Pack actualizado correctamente',
            'pack' => $pack->load('composicion.producto'),
        ], 200);
    }

    // Delete a pack
    public function destroy($id)
    {
        $pack = PackProducto::findOrFail($id);

        // Eliminar la composición del pack
        PackComposicion::where('sku_pack', $pack->id)->delete();

        // Eliminar el pack
        $pack->delete();

        return response()->json(['message' => 'Pack eliminado correctamente'], 200);
    }
}
