<?php

namespace App\Http\Controllers;

use App\Models\StockProducto;
use App\Models\Producto;
use Illuminate\Http\Request;

class StockController extends Controller
{
    // Get all stock from products
    public function index()
    {
        $stocks = StockProducto::with('producto')->get();

        return response()->json($stocks, 200);
    }

    // Create a stock record (initial entry)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku_producto' => 'required|exists:productos,id',
            'cantidad' => 'required|integer|min:0',
        ]);

        $stock = StockProducto::create([
            'sku_producto' => $validated['sku_producto'],
            'cantidad' => $validated['cantidad'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Stock registrado correctamente',
            'stock' => $stock
        ], 201);
    }

    // Update the stock (for example, add or subtract units)
    public function update(Request $request, $id)
    {
        $stock = StockProducto::findOrFail($id);

        $validated = $request->validate([
            'cantidad' => 'required|integer',
        ]);

        // Update stock ammount
        $stock->update([
            'cantidad' => $stock->cantidad + $validated['cantidad'], // Add or subtract units
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Stock actualizado correctamente',
            'stock' => $stock
        ], 200);
    }

    // Get stock by id
    public function show($id)
    {
        $stock = StockProducto::with('producto')->findOrFail($id);

        return response()->json($stock, 200);
    }

    // Delete register of stock
    public function destroy($id)
    {
        $stock = StockProducto::findOrFail($id);
        $stock->delete();

        return response()->json([
            'message' => 'Stock eliminado correctamente'
        ], 200);
    }
}
