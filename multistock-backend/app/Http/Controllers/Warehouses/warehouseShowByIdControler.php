<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseShowByIdController{
    /**
     * Get a specific warehouse by ID.
     */

     public function warehouse_show($id)
     {
         try {
             $warehouse = Warehouse::with('company')->findOrFail($id);
             return response()->json(['message' => 'Bodega encontrada con éxito.', 'data' => $warehouse], 200);
         } catch (\Exception $e) {
             return response()->json(['message' => 'Bodega no encontrada.', 'error' => $e->getMessage()], 404);
         }
     }
}