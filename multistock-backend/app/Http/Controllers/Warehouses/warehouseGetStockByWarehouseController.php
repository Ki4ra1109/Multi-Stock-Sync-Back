<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseGetStockByWarehouseController{
    /**
     * Get stock by warehouse.
     */
    public function getStockByWarehouse($warehouse_id)
    {
        try {
            $warehouse = Warehouse::with('stockWarehouses')->findOrFail($warehouse_id);
            return response()->json(['message' => 'Stock encontrado con éxito.', 'data' => $warehouse->stockWarehouses], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Bodega no encontrada.', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el stock.', 'error' => $e->getMessage()], 500);
        }
    }
}