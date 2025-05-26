<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class getProductByCompanyIdController{

    public function getProductByCompanyId($idCompany){
        try {
            $stock = DB::table('stock_warehouses')
            ->where('warehouse_id', $idCompany)
            ->where('available_quantity', '>', 0)
            ->select(
                'stock_warehouses.id',
                'stock_warehouses.id_mlc',
                'stock_warehouses.title',
                'stock_warehouses.price',
                'stock_warehouses.available_quantity',
            )
            ->get();
            return response()->json(['message' => 'Stock encontrado con éxito.', 'data' => $stock], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Stock no encontrado.', 'error' => $e->getMessage()], 404);
        }
    }

}
