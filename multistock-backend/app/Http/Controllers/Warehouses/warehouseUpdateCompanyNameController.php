<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class warehouseUpdateCompanyNameController{
    /**
     * Update a company's name.
     */

     public function company_update(Request $request, $id)
     {
         $validated = $request->validate([
             'name' => 'nullable|string|max:100',
         ]);
 
         if (empty($validated)) {
             return response()->json(['message' => 'Debes enviar al menos un campo para actualizar.', 'fields' => ['name']], 422);
         }
 
         try {
             $company = Company::findOrFail($id);
             $company->update(array_filter($validated));
 
             return response()->json(['message' => 'Nombre de la empresa actualizado con éxito.', 'data' => $company], 200);
         } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
             return response()->json(['message' => 'Empresa no encontrada.', 'error' => $e->getMessage()], 404);
         } catch (\Exception $e) {
             return response()->json(['message' => 'Error al actualizar la empresa.', 'error' => $e->getMessage()], 500);
         }
     }
}