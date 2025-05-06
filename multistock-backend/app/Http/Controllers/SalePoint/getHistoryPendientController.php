<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class getHistoryPendientController
{
    public function getHistoryPendient($clientId)
    {
        try {
            
            $historySale = DB::Table('sale')
                ->join('warehouses', 'sale.warehouse_id', '=', 'warehouses.id')
                ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
                ->select(
                    'sale.*',
                    'warehouses.name as warehouse_name'
                )
                ->where('sale.status_sale', "Pendiente")
                ->where('companies.client_id', $clientId)
                ->get();

            return response()->json([
                'message' => 'Historial de ventas obtenido correctamente',
                'data' => $historySale,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el historial de ventas: ' . $e->getMessage()], 500);
        }
    }
}