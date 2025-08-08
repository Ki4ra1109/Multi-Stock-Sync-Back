<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseDeleteWarehouseByIdController{
    /**
     * Delete a specific warehouse by ID.
     */
    public function warehouse_delete($id)
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json(['message' => 'No se encontró la bodega especificada.'], 404);
        }

        // Evitar eliminar si tiene productos asociados en stock_warehouses
        $hasAssociatedProducts = $warehouse->stockWarehouses()->exists();
        if ($hasAssociatedProducts) {
            return response()->json([
                'message' => 'No es posible eliminar la bodega porque registra productos asociados.'
            ], 409);
        }

        $warehouse->delete();
        return response()->json(['message' => 'La bodega se eliminó correctamente.']);
    }
}