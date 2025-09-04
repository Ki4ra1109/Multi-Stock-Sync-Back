<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseUpdateStockForWarehouseController{
       /**
     * Update stock for a warehouse.
     */
    public function stock_update(Request $request, $id_mlc)
    {
        $validated = $request->validate([
            'thumbnail' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'price_clp' => 'nullable|numeric',
            'warehouse_stock' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        if (empty(array_filter($validated))) {
            return response()->json([
                'message' => 'Debes enviar al menos un campo para actualizar.',
                'fields' => ['thumbnail', 'title', 'price_clp', 'warehouse_stock', 'warehouse_id']
            ], 422);
        }

        try {
            // Buscar por ID de MercadoLibre (id_mlc)
            $stock = StockWarehouse::where('id_mlc', $id_mlc)->firstOrFail();
            $stock->update(array_filter($validated));

            return response()->json(['message' => 'Stock actualizado con éxito.', 'data' => $stock], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Stock no encontrado con ese id_mlc.', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el stock.', 'error' => $e->getMessage()], 500);
        }
    }
}