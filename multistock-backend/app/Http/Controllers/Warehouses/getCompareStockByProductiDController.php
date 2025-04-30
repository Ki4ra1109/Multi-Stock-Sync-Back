<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class getCompareStockByProductiDController
{
    public function getCompareStockByProductiD($id_mlc)
    {
        try {
            $stock = StockWarehouse::where('id_mlc', $id_mlc)->get();
            return response()->json(['message' => 'Stock encontrado con éxito.', 'data' => $stock], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Stock no encontrado.', 'error' => $e->getMessage()], 404);
        }
    }
}