<?php

namespace App\Http\Controllers;

use App\Models\StockProducto;
use App\Models\Producto;
use App\Models\StockWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'sku_producto' => 'required|exists:productos,sku', // Check SKU exists in productos table
            'cantidad' => 'required|integer|min:0',
        ]);

        // Search producto by SKU
        $producto = Producto::where('sku', $validated['sku_producto'])->firstOrFail();

        // Create stock record
        $stock = StockProducto::create([
            'sku_producto' => $producto->sku, // Use producto SKU
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

        // Update stock amount
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

    /**
     * Obtener stock de productos en bodegas
     */
    public function getWarehouseStock()
    {
        try {
            $warehouseStock = StockWarehouse::with(['warehouse', 'warehouse.company'])->get();

            return response()->json([
                'message' => 'Stock de bodegas obtenido correctamente',
                'warehouse_stock' => $warehouseStock,
                'status' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener stock de bodegas', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al obtener stock de bodegas',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Obtener stock de una bodega específica
     */
    public function getStockByWarehouse($warehouseId)
    {
        try {
            $warehouse = Warehouse::with('company')->findOrFail($warehouseId);
            $stock = StockWarehouse::where('warehouse_id', $warehouseId)->get();

            return response()->json([
                'message' => 'Stock de bodega obtenido correctamente',
                'warehouse' => $warehouse,
                'stock' => $stock,
                'total_items' => $stock->count(),
                'status' => 'success'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Bodega no encontrada',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener stock de bodega', [
                'warehouse_id' => $warehouseId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al obtener stock de bodega',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Actualizar stock de un producto en bodega
     */
    public function updateWarehouseStock(Request $request, $stockId)
    {
        try {
            $stock = StockWarehouse::findOrFail($stockId);

            $validated = $request->validate([
                'available_quantity' => 'sometimes|integer|min:0',
                'price' => 'sometimes|numeric|min:0',
                'condicion' => 'sometimes|string|max:255',
                'currency_id' => 'sometimes|string|max:255',
                'listing_type_id' => 'sometimes|string|max:255',
                'category_id' => 'nullable|string|max:255',
                'attribute' => 'nullable|array',
                'pictures' => 'nullable|array',
                'sale_terms' => 'nullable|array',
                'shipping' => 'nullable|array',
                'description' => 'nullable|string'
            ]);

            if (empty(array_filter($validated))) {
                return response()->json([
                    'message' => 'Debes enviar al menos un campo para actualizar.',
                    'fields' => ['available_quantity', 'price', 'condicion', 'currency_id', 'listing_type_id', 'category_id', 'attribute', 'pictures', 'sale_terms', 'shipping', 'description']
                ], 422);
            }

            // Procesar campos JSON
            if (isset($validated['attribute'])) {
                $validated['attribute'] = json_encode($validated['attribute']);
            }
            if (isset($validated['pictures'])) {
                $validated['pictures'] = json_encode($validated['pictures']);
            }
            if (isset($validated['sale_terms'])) {
                $validated['sale_terms'] = json_encode($validated['sale_terms']);
            }
            if (isset($validated['shipping'])) {
                $validated['shipping'] = json_encode($validated['shipping']);
            }

            $stock->update(array_filter($validated));

            Log::info('Stock de bodega actualizado', [
                'stock_id' => $stockId,
                'updated_fields' => array_keys(array_filter($validated))
            ]);

            return response()->json([
                'message' => 'Stock de bodega actualizado correctamente',
                'stock' => $stock->fresh(),
                'status' => 'success'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Stock no encontrado',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar stock de bodega', [
                'stock_id' => $stockId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al actualizar stock de bodega',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Crear stock de producto en bodega
     */
    public function createWarehouseStock(Request $request)
    {
        try {
            $validated = $request->validate([
                'id_mlc' => 'nullable|string|max:255',
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'title' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'available_quantity' => 'required|integer|min:0',
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

            // Crear producto en bodega
            $stock = StockWarehouse::create([
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

            Log::info('Stock de bodega creado', [
                'stock_id' => $stock->id,
                'warehouse_id' => $validated['warehouse_id'],
                'title' => $validated['title']
            ]);

            return response()->json([
                'message' => 'Stock de bodega creado correctamente',
                'stock' => $stock->load('warehouse'),
                'status' => 'success'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
                'status' => 'error'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear stock de bodega', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear stock de bodega',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Eliminar stock de producto en bodega
     */
    public function deleteWarehouseStock($stockId)
    {
        try {
            $stock = StockWarehouse::findOrFail($stockId);
            $stock->delete();

            Log::info('Stock de bodega eliminado', [
                'stock_id' => $stockId
            ]);

            return response()->json([
                'message' => 'Stock de bodega eliminado correctamente',
                'status' => 'success'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Stock no encontrado',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar stock de bodega', [
                'stock_id' => $stockId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al eliminar stock de bodega',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Obtener stock por empresa
     */
    public function getStockByCompany($companyId)
    {
        try {
            $stock = StockWarehouse::whereHas('warehouse', function($query) use ($companyId) {
                $query->where('assigned_company_id', $companyId);
            })->with(['warehouse', 'warehouse.company'])->get();

            return response()->json([
                'message' => 'Stock por empresa obtenido correctamente',
                'stock' => $stock,
                'total_items' => $stock->count(),
                'status' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener stock por empresa', [
                'company_id' => $companyId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al obtener stock por empresa',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}
