<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class getWarehouseByCompanyIdController{

     public function getWarehouseByCompany($idCompany)
     {
         try {
            $stock = DB::table('warehouses')
            ->join('companies', 'warehouses.assigned_company_id', '=', 'companies.id')
            ->where('warehouses.assigned_company_id', $idCompany)
            ->select(
                'warehouses.*',
                'companies.name as company_name',
            )
            ->get();
            return response()->json(['message' => 'Stock encontrado con éxito.', 'data' => $stock], 200);
         } catch (\Exception $e) {
             return response()->json(['message' => 'Bodega no encontrada.', 'error' => $e->getMessage()], 404);
         }
     }
}