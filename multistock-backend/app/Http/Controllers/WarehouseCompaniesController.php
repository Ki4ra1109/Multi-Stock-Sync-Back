<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseCompaniesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companies = Company::with('warehouses')->get();
        return response()->json($companies);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Return a view for creating a new company or warehouse (if applicable).
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'location' => 'nullable|string|max:255',
                'assigned_company_id' => 'required_if:is_warehouse,true|exists:companies,id',
            ], [
                'name.required' => 'El nombre es requerido.',
                'name.string' => 'El nombre debe ser una cadena de texto.',
                'name.max' => 'El nombre no puede tener más de 100 caracteres.',
                'location.string' => 'La ubicación debe ser una cadena de texto.',
                'location.max' => 'La ubicación no puede tener más de 255 caracteres.',
                'assigned_company_id.required_if' => 'La empresa asignada es requerida para bodegas.',
                'assigned_company_id.exists' => 'La empresa asignada debe existir.',
            ]);

            if ($request->is('api/companies')) {
                $company = Company::create(['name' => $validated['name']]);
                return response()->json(['message' => 'Empresa creada con éxito.', 'data' => $company], 201);
            } elseif ($request->is('api/warehouses')) {
                if (!isset($validated['assigned_company_id'])) {
                    $errors = ['assigned_company_id' => ['La empresa asignada es requerida para bodegas.']];
                    if (!isset($validated['name'])) {
                        $errors['name'] = ['El nombre es requerido.'];
                    }
                    return response()->json([
                        'message' => 'Datos de validación incorrectos.',
                        'errors' => $errors
                    ], 422);
                }
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
     * Display the specified resource.
     */
    public function show($id)
    {
        $company = Company::with('warehouses')->findOrFail($id);
        return response()->json($company);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        // Return a view for editing a company or warehouse (if applicable).
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:255',
            'assigned_company_id' => 'required|exists:companies,id',
        ], [
            'name.required' => 'El nombre es requerido.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede tener más de 100 caracteres.',
            'location.string' => 'La ubicación debe ser una cadena de texto.',
            'location.max' => 'La ubicación no puede tener más de 255 caracteres.',
            'assigned_company_id.required' => 'La empresa asignada es requerida.',
            'assigned_company_id.exists' => 'La empresa asignada debe existir.',
        ]);

        if ($request->is('companies/*')) {
            $company = Company::findOrFail($id);
            $company->update(['name' => $validated['name']]);
            return response()->json(['message' => 'Empresa actualizada con éxito.', 'data' => $company]);
        } elseif ($request->is('warehouses/*')) {
            $warehouse = Warehouse::findOrFail($id);
            $warehouse->update([
                'name' => $validated['name'],
                'location' => $validated['location'] ?? 'no especificado',
                'assigned_company_id' => $validated['assigned_company_id'],
            ]);
            return response()->json(['message' => 'Bodega actualizada con éxito.', 'data' => $warehouse]);
        }
    }

    /**
     * Remove the specified resource from storage.
    */

    public function destroy($id)
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
