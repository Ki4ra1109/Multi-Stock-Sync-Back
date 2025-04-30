<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseCreateProductStockWarehouseController{
    /**
     * Crear un nuevo producto
     */
    /**
     * Crear un nuevo producto manualmente en la base de datos (sin buscar en MercadoLibre).
     */
    public function stock_store_by_url(Request $request)
    {
        try {
            // Validar los datos recibidos en el Body
            $validated = $request->validate([
                'id_mlc' => 'nullable|string|max:255',
                'warehouse_id' => 'required|integer',
                'title' => 'required|string|max:255',
                'price' => 'required|numeric',
                'available_quantity' => 'required|integer',
                'condicion' => 'required|string|max:255',
                'currency_id' => 'required|string|max:255',
                'listing_type_id' => 'required|string|max:255',
                'category_id' => 'nullable|string|max:255',
                'attribute' => 'nullable|array',
                'pictures' => 'nullable|array',
                'sale_terms' => 'nullable|array',
                'shipping' => 'nullable|array',
                'description' => 'nullable|string'
            ]);

            // Crear producto en base de datos
            $stock = \App\Models\StockWarehouse::create([
                'id_mlc' => $validated['id_mlc'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'title' => $validated['title'],
                'price' => $validated['price'],
                'condicion' => $validated['condicion'],
                'currency_id' => $validated['currency_id'],
                'listing_type_id' => $validated['listing_type_id'],
                'available_quantity' => $validated['available_quantity'],
                'category_id' => $validated['category_id'] ?? null,
                'attribute' => isset($validated['attribute']) ? json_encode($validated['attribute']) : json_encode([]),
                'pictures' => isset($validated['pictures']) ? json_encode($validated['pictures']) : json_encode([]),
                'sale_terms' => isset($validated['sale_terms']) ? json_encode($validated['sale_terms']) : json_encode([]),
                'shipping' => isset($validated['shipping']) ? json_encode($validated['shipping']) : json_encode([]),
                'description' => $validated['description'] ?? '',
            ]);

            return response()->json([
                'message' => 'Producto creado manualmente con éxito en la base de datos.',
                'data' => $stock,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error inesperado al guardar el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}