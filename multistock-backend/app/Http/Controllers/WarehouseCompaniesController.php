<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseCompaniesController extends Controller
{
    /**
     * List all companies with their warehouses.
     */
    public function company_list_all()
    {
        $companies = Company::with('warehouses')->get();
        return response()->json($companies);
    }

    /**
     * Create a new company or warehouse.
     */
    public function company_or_warehouse_store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'location' => 'nullable|string|max:255',
                'assigned_company_id' => 'required_if:is_warehouse,true|exists:companies,id',
            ]);

            if ($request->is('api/companies')) {
                $company = Company::create(['name' => $validated['name']]);
                return response()->json(['message' => 'Empresa creada con éxito.', 'data' => $company], 201);
            } elseif ($request->is('api/warehouses')) {
                $warehouse = Warehouse::create([
                    'name' => $validated['name'],
                    'location' => $validated['location'] ?? 'no especificado',
                    'assigned_company_id' => $validated['assigned_company_id'],
                ]);
                return response()->json(['message' => 'Bodega creada con éxito.', 'data' => $warehouse], 201);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos de validación incorrectos.', 'errors' => $e->errors()], 422);
        }
    }

    /**
     * List a warehouse by its ID.
     */
    public function warehouse_list_by_id($id)
    {
        try {
            $warehouse = Warehouse::with('company')->findOrFail($id);
            return response()->json(['message' => 'Bodega encontrada con éxito.', 'data' => $warehouse], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Bodega no encontrada.', 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * List a company by its ID.
     */
    public function company_list_by_id($id)
    {
        try {
            $company = Company::with('warehouses')->findOrFail($id);
            return response()->json(['message' => 'Empresa encontrada con éxito.', 'data' => $company], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Empresa no encontrada.', 'error' => $e->getMessage()], 404);
        }
    }


    /**
     * Update a company's name.
     */
    public function company_update_name(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
            ]);

            $company = Company::findOrFail($id);
            $company->name = $validated['name'];
            $company->save();

            return response()->json(['message' => 'Nombre de la empresa actualizado con éxito.', 'data' => $company], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos de validación incorrectos.', 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Delete a company or warehouse.
     */
    public function company_or_warehouse_delete($id)
    {
        $company = Company::find($id);

        if ($company) {
            $company->delete();
            return response()->json(['message' => 'Empresa eliminada con éxito.']);
        }

        $warehouse = Warehouse::find($id);

        if ($warehouse) {
            $warehouse->delete();
            return response()->json(['message' => 'Bodega eliminada con éxito.']);
        }

        return response()->json(['message' => 'Recurso no encontrado.'], 404);
    }
}
