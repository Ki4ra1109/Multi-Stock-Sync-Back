<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseNewWarehouseStoreController{
    /**
     * Create a new warehouse.
     */
    public function warehouse_store(Request $request)
    {
        \Log::info('Entró al método warehouse_store');
    
        try {
            // Validate the request data
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'location' => 'nullable|string|max:255',
                'assigned_company_id' => 'required|integer',
            ]);
    
            \Log::info('Datos validados:', $validated);
    
            // Check if assigned_company_id exists in the companies table
            $company = Company::find($validated['assigned_company_id']);
    
            if (!$company) {
                \Log::error('Empresa no encontrada con ID:', ['assigned_company_id' => $validated['assigned_company_id']]);
                return response()->json(['message' => 'La empresa asignada no existe.'], 404);
            }
    
            // Insert data
            $warehouse = Warehouse::create([
                'name' => $validated['name'],
                'location' => $validated['location'] ?? 'no especificado',
                'assigned_company_id' => $validated['assigned_company_id'],
            ]);
    
            \Log::info('Bodega creada:', $warehouse->toArray());
    
            return response()->json(['message' => 'Bodega creada con éxito.', 'data' => $warehouse], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación:', $e->errors());
            return response()->json(['message' => 'Datos inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Error inesperado:', ['exception' => $e->getMessage()]);
            return response()->json(['message' => 'Error al crear la bodega.', 'error' => $e->getMessage()], 500);
        }
    }
}