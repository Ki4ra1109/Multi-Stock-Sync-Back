<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseDeleteCompanyByIdController{
    /**
     * Delete a specific company by ID.
     */
    public function company_delete($id)
    {
        $company = Company::find($id);

        if ($company) {
            $company->delete();
            return response()->json(['message' => 'Empresa eliminada con éxito.']);
        }

        return response()->json(['message' => 'Empresa no encontrada.'], 404);
    }
}