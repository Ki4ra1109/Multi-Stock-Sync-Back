<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseUpdateDetailsController{
    /**
     * Update a warehouse's details.
     */
    public function warehouse_update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'assigned_company_id' => 'nullable|integer',
        ]);

        if (empty($validated)) {
            return response()->json(['message' => 'Debes enviar al menos un campo para actualizar.', 'fields' => ['name', 'location', 'assigned_company_id']], 422);
        }

        try {
            // Check if assigned_company_id exists in the companies table, if provided
            if (isset($validated['assigned_company_id'])) {
                $company = Company::find($validated['assigned_company_id']);
                if (!$company) {
                    return response()->json(['message' => 'La empresa asignada no existe.'], 404);
                }
            }

            // Search warehouse and update data
            $warehouse = Warehouse::findOrFail($id);
            $warehouse->update(array_filter($validated));

            return response()->json(['message' => 'Detalles de la bodega actualizados con éxito.', 'data' => $warehouse], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Bodega no encontrada.', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la bodega.', 'error' => $e->getMessage()], 500);
        }
    }
}