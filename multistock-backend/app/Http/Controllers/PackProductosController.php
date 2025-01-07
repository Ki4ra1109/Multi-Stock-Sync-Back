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
        $packs = PackProducto::with('composiciones.producto')->get();

        return response()->json($packs, 200);
    }

    // Create a new pack
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku_pack' => 'nullable|string|unique:pack_productos,sku_pack|max:255', // Optional SKU
            'nombre' => 'required|string|max:255',
            'composicion' => 'required|array',
            'composicion.*.sku_producto' => 'required|exists:productos,sku', // Validate SKU
            'composicion.*.cantidad_pack' => 'required|integer|min:1',
        ]);

        // Generate SKU automatically if not sent
        if (empty($validated['sku_pack'])) {
            $validated['sku_pack'] = $this->generateSku($validated['nombre']);
        }

        // Create pack
        $pack = PackProducto::create([
            'sku_pack' => $validated['sku_pack'], // Ensure sku_pack is included
            'nombre' => $validated['nombre'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Save pack composition
        foreach ($validated['composicion'] as $componente) {
            $producto = Producto::where('sku', $componente['sku_producto'])->firstOrFail();

            PackComposicion::create([
                'sku_pack' => $pack->sku_pack,
                'sku_producto' => $producto->sku,
                'cantidad_pack' => $componente['cantidad_pack'],
            ]);
        }

        return response()->json([
            'message' => 'Pack creado correctamente',
            'pack' => $pack->load('composiciones.producto'),
        ], 201);
    }

    // Get a single pack by id
    public function show($id)
    {
        $pack = PackProducto::with('composiciones.producto')->findOrFail($id);

        return response()->json($pack, 200);
    }

    // Update a pack
    public function update(Request $request, $id)
    {
        $pack = PackProducto::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'sku_pack' => 'nullable|string|unique:pack_productos,sku_pack|max:255', // Validate SKU if not sent
            'composicion' => 'sometimes|array',
            'composicion.*.sku_producto' => 'required_with:composicion|exists:productos,sku', // Validate product SKU
            'composicion.*.cantidad_pack' => 'required_with:composicion|integer|min:1',
        ]);

        // Generate auto SKU if not sent and name is sent  
        if (empty($validated['sku_pack']) && isset($validated['nombre'])) {
            $validated['sku_pack'] = $this->generateSku($validated['nombre']);
        }

        // Update Pack
        $pack->update([
            'nombre' => $validated['nombre'] ?? $pack->nombre,
            'sku_pack' => $validated['sku_pack'] ?? $pack->sku_pack,
            'updated_at' => now(),
        ]);

        // If sent new composition, update it
        if (isset($validated['composicion'])) {
            // Delete the old composition
            PackComposicion::where('sku_pack', $pack->sku_pack)->delete();

            // Create new composition
            foreach ($validated['composicion'] as $componente) {
                $producto = Producto::where('sku', $componente['sku_producto'])->firstOrFail();

                PackComposicion::create([
                    'sku_pack' => $pack->sku_pack,
                    'sku_producto' => $producto->sku,
                    'cantidad_pack' => $componente['cantidad_pack'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Pack actualizado correctamente',
            'pack' => $pack->load('composiciones.producto'),
        ], 200);
    }

    // Delete a pack
    public function destroy($id)
    {
        $pack = PackProducto::findOrFail($id);

        // Delete pack composition
        PackComposicion::where('sku_pack', $pack->sku_pack)->delete();

        // Delete pack
        $pack->delete();

        return response()->json(['message' => 'Pack eliminado correctamente'], 200);
    }

    //Generate new SKU
    private function generateSku($nombre)
    {
        // Create SKU based in the first 3 letters of the name and a random number
        $baseSku = strtoupper(substr($nombre, 0, 3)); // 3 first letters of the name
        $randomNumber = rand(1000, 9999); // Generate 4 random numbers
        return "{$baseSku}-{$randomNumber}";
    }
}
