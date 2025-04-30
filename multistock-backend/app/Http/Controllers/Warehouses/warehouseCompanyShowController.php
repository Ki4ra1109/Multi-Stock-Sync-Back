<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseCompanyShowController{
    /**
     * Get a specific company by ID.
     */
    
     public function company_show($id)
     {
         try {
             $company = Company::with('warehouses')->findOrFail($id);
             return response()->json(['message' => 'Empresa encontrada con éxito.', 'data' => $company], 200);
         } catch (\Exception $e) {
             return response()->json(['message' => 'Empresa no encontrada.', 'error' => $e->getMessage()], 404);
         }
     }
}