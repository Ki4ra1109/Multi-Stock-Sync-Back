<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseCompaniesController extends Controller
{
    /**
     * List all companies.
     */
    public function company_list_all()
    {
        $companies = Company::with('warehouses')->get();
        return response()->json($companies);
    }

    /**
     * List all warehouses.
     */
    public function warehouse_list_all()
    {
        $warehouses = Warehouse::with('company')->get();
        return response()->json($warehouses);
    }

    /**
     * Create a new company.
     */
    public function company_store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $company = Company::create(['name' => $validated['name']]);

        return response()->json(['message' => 'Empresa creada con éxito.', 'data' => $company], 201);
    }

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

    /**
     * Get a specific warehouse by ID.
     */

    public function warehouse_show($id)
    {
        try {
            $warehouse = Warehouse::with('company')->findOrFail($id);
            return response()->json(['message' => 'Bodega encontrada con éxito.', 'data' => $warehouse], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Bodega no encontrada.', 'error' => $e->getMessage()], 404);
        }
    }

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

    /**
     * Delete a specific warehouse by ID.
     */
    public function warehouse_delete($id)
    {
        $warehouse = Warehouse::find($id);

        if ($warehouse) {
            $warehouse->delete();
            return response()->json(['message' => 'Bodega eliminada con éxito.']);
        }

        return response()->json(['message' => 'Bodega no encontrada.'], 404);
    }
}
