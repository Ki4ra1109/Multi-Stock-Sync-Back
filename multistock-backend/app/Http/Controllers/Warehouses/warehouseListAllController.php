<?php

namespace App\Http\Controllers\Warehouses;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\StockWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class warehouseListAllController extends Controller {
    /**
     * Lista todas las bodegas con su información relacionada.
     * Permite filtrar por compañía y fecha de creación.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        Log::info('Iniciando listado de bodegas', [
            'request_data' => $request->all(),
            'user_id' => $request->user() ? $request->user()->id : 'no autenticado'
        ]);

        try {
            // Obtener parámetros de filtrado
            $perPage = $request->input('per_page', 15);
            $companyId = $request->input('company_id');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $orderBy = $request->input('order_by', 'created_at');
            $orderDirection = $request->input('order_direction', 'desc');

            Log::info('Parámetros de filtrado', [
                'per_page' => $perPage,
                'company_id' => $companyId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'order_by' => $orderBy,
                'order_direction' => $orderDirection
            ]);
            
            // Construir la consulta base
            $query = Warehouse::with([
                'company:id,name,client_id',
                'stockWarehouses:id,warehouse_id,title,price,available_quantity,id_mlc'
            ])->select([
                'warehouses.id',
                'warehouses.name',
                'warehouses.location',
                'warehouses.assigned_company_id',
                'warehouses.created_at',
                'warehouses.updated_at'
            ]);

            // Aplicar filtros
            if ($companyId) {
                $query->where('assigned_company_id', $companyId);
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            // Aplicar ordenamiento
            $allowedOrderFields = ['created_at', 'name', 'location'];
            $orderBy = in_array($orderBy, $allowedOrderFields) ? $orderBy : 'created_at';
            $orderDirection = in_array($orderDirection, ['asc', 'desc']) ? $orderDirection : 'desc';
            
            $query->orderBy($orderBy, $orderDirection);

            // Verificar si hay bodegas que coincidan con los filtros
            $totalWarehouses = $query->count();
            Log::info('Total de bodegas encontradas', ['total' => $totalWarehouses]);

            if ($totalWarehouses === 0) {
                Log::warning('No se encontraron bodegas con los filtros especificados');
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'No hay bodegas que coincidan con los filtros especificados'
                ], 200);
            }
            
            // Obtener las bodegas con paginación
            $warehouses = $query->paginate($perPage);

            // Log para verificar la estructura de la respuesta
            Log::info('Estructura de la primera bodega', [
                'sample_warehouse' => $warehouses->first() ? [
                    'id' => $warehouses->first()->id,
                    'name' => $warehouses->first()->name,
                    'company' => $warehouses->first()->company ? [
                        'id' => $warehouses->first()->company->id,
                        'name' => $warehouses->first()->company->name
                    ] : null
                ] : null
            ]);

            Log::info('Bodegas recuperadas exitosamente', [
                'total_results' => $warehouses->total(),
                'current_page' => $warehouses->currentPage(),
                'per_page' => $warehouses->perPage()
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $warehouses,
                'message' => 'Bodegas listadas exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener las bodegas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las bodegas: ' . $e->getMessage()
            ], 500);
        }
    }
}