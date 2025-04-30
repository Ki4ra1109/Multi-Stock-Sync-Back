<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class getPriceNetoStockController
{
    public function getPriceNetoStock($idCompany){
        try {
            $stock = DB::table('stock_warehouses')
            ->join('warehouses', 'stock_warehouses.warehouse_id', '=', 'warehouses.id')
            ->join('companies', 'warehouses.assigned_company_id', '=', 'companies.id')
            ->where('warehouses.assigned_company_id', $idCompany)
            ->where('stock_warehouses.available_quantity', '>', 0)
            ->select(
                'stock_warehouses.id_mlc',
                'stock_warehouses.title',
                'stock_warehouses.price',
                'stock_warehouses.available_quantity',
                DB::raw('stock_warehouses.available_quantity * stock_warehouses.price as total_price'),
                DB::raw('(stock_warehouses.available_quantity * stock_warehouses.price * 0.19 ) as iva'),
                DB::raw('stock_warehouses.available_quantity * stock_warehouses.price - (stock_warehouses.available_quantity * stock_warehouses.price * 0.19 ) as price_neto'),
                'warehouses.name as warehouse_name',
                'companies.name as company_name',
                'companies.client_id'
            )
            ->get();
            return response()->json(['message' => 'Stock encontrado con éxito.', 'data' => $stock], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Stock no encontrado.', 'error' => $e->getMessage()], 404);
        }
    }
    
}