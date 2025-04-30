<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseListAllController{
    /**
     * List all warehouses.
     */
    public function warehouse_list_all()
    {
        $warehouses = Warehouse::with('company')->get();
        return response()->json($warehouses);
    }
}