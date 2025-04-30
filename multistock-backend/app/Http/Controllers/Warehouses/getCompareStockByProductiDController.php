<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class getCompareStockByProductiDController
{
    public function getCompareStockByProductiD($id_mlc, $idCompany)
    {
        try {
            $stock = DB::table('stock_warehouses')
            ->join('warehouses', 'stock_warehouses.warehouse_id', '=', 'warehouses.id')
            ->join('companies', 'warehouses.assigned_company_id', '=', 'companies.id')
            ->where('stock_warehouses.id_mlc', $id_mlc)
            ->where('warehouses.assigned_company_id', $idCompany)
            ->select(
                'stock_warehouses.*',
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