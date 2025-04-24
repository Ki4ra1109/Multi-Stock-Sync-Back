<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
        \Log::error('Error al crear empresa por URL:', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'Error al crear la empresa.', 'error' => $e->getMessage()], 500);
    }
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

    /**
     * Get stock by warehouse.
     */
    public function getStockByWarehouse($warehouse_id)
    {
        try {
            $warehouse = Warehouse::with('stockWarehouses')->findOrFail($warehouse_id);
            return response()->json(['message' => 'Stock encontrado con éxito.', 'data' => $warehouse->stockWarehouses], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Bodega no encontrada.', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el stock.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
 * Create stock for a warehouse via URL parameters.
 */
public function stock_store_by_url($id_mlc, $warehouse_id, $stock, $client_id)
{
    try {
        // Verifica existencia de la bodega
        $warehouse = Warehouse::findOrFail($warehouse_id);

        // Verifica existencia y validez del token del cliente
        $credentials = \App\Models\MercadoLibreCredential::where('client_id', $client_id)->first();
        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['message' => 'Token inválido o expirado para el cliente.'], 401);
        }

        // Llama a la API de MercadoLibre
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/items/{$id_mlc}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'No se pudo obtener el producto desde MercadoLibre.',
                'status' => $response->status(),
                'error' => $response->json()
            ], $response->status());
        }

        $productData = $response->json();
        $thumbnail = $productData['thumbnail'] ?? 'no asignado';
        $title = $productData['title'] ?? 'no asignado';
        $price_clp = $productData['price'] ?? 0;

        // Crear el stock
        $stockRecord = StockWarehouse::create([
            'id_mlc' => $id_mlc,
            'title' => $title,
            'thumbnail' => $thumbnail,
            'price_clp' => $price_clp,
            'warehouse_stock' => $stock,
            'warehouse_id' => $warehouse_id,
        ]);

        return response()->json(['message' => 'Producto agregado con éxito.', 'data' => $stockRecord], 201);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al agregar el producto.', 'error' => $e->getMessage()], 500);
    }
}



    /**
     * Update stock for a warehouse.
     */
    public function stock_update(Request $request, $id_mlc)
{
    $validated = $request->validate([
        'thumbnail' => 'nullable|string|max:255',
        'title' => 'nullable|string|max:255',
        'price_clp' => 'nullable|numeric',
        'warehouse_stock' => 'nullable|integer',
        'warehouse_id' => 'nullable|integer|exists:warehouses,id',
    ]);

    if (empty(array_filter($validated))) {
        return response()->json([
            'message' => 'Debes enviar al menos un campo para actualizar.',
            'fields' => ['thumbnail', 'title', 'price_clp', 'warehouse_stock', 'warehouse_id']
        ], 422);
    }

    try {
        // Buscar por ID de MercadoLibre (id_mlc)
        $stock = StockWarehouse::where('id_mlc', $id_mlc)->firstOrFail();
        $stock->update(array_filter($validated));

        return response()->json(['message' => 'Stock actualizado con éxito.', 'data' => $stock], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['message' => 'Stock no encontrado con ese id_mlc.', 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al actualizar el stock.', 'error' => $e->getMessage()], 500);
    }
}

    /**
     * Delete stock for a warehouse.
     */
    public function stock_delete($id)
    {
        try {
            $stock = StockWarehouse::findOrFail($id);
            $stock->delete();
            return response()->json(['message' => 'Stock eliminado con éxito.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Stock no encontrado.', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el stock.', 'error' => $e->getMessage()], 500);
        }
    }

}
