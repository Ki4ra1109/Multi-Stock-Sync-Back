<?php

namespace App\Http\Controllers\SalePoint;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class getAllHistorySaleFinishController extends Controller
{
    public function getAllHistorySaleFinish($clientId)
    {
        try {
            
            $historySale = DB::Table('sale')
                ->join('warehouses', 'sale.warehouse_id', '=', 'warehouses.id')
                ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
                ->join('clientes', 'clientes.id', '=', 'sale.client_id')
                ->select(
                    'sale.id as id_folio',
                    'sale.warehouse_id',
                    "clientes.nombres",
                    "clientes.apellidos",
                    "sale.type_emission",
                    'sale.status_sale',
                    'warehouses.name as warehouse_name'
                )
                ->where('sale.status_sale', "Finalizado")
                ->where('companies.client_id', $clientId)
                ->get();

            return response()->json([
                'message' => 'Historial de ventas Finalizadas obtenido correctamente',
                'data' => $historySale,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el historial de ventas: ' . $e->getMessage()], 500);
        }
    }
}