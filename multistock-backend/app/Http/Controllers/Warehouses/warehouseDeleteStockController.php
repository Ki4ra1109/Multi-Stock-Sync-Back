<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseDeleteStockController{
    /**
     * Delete stock for a warehouse.
     */
    public function stock_delete($id)
    {
        try {
            $stock = StockWarehouse::findOrFail($id);
            $stock->delete();
            return response()->json(['message' => 'Stock eliminado con éxito.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Stock no encontrado.', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el stock.', 'error' => $e->getMessage()], 500);
        }
    }
}