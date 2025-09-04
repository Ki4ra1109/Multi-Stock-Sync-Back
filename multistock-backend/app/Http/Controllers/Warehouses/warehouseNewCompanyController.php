<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class warehouseNewCompanyController{
        /**
     * Create a new company.
     */
    public function company_store_by_url($name, $client_id)
    {
        try {
            // Validación básica
            if (empty($name) || !is_numeric($client_id)) {
                return response()->json(['message' => 'Parámetros inválidos.'], 422);
            }

            // Crear empresa
            $company = Company::create([
                'name' => $name,
                'client_id' => $client_id,
            ]);

            return response()->json([
                'message' => 'Empresa creada con éxito por URL.',
                'data' => $company
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear empresa por URL:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al crear la empresa.', 'error' => $e->getMessage()], 500);
        }
    }
}