<?php

namespace App\Http\Controllers\SalePoint;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class getDeleteHistoryByIdSaleController extends Controller
{
    public function getDeleteHistoryByIdSale($companyId, $saleId)
    {
        try {

            $saleDeleted = DB::table('sale')
                ->join('warehouses', 'sale.warehouse_id', '=', 'warehouses.id')
                ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
                ->select(
                    'sale.*',
                    'warehouses.name as warehouse_name'
                )
                ->where('sale.id', $saleId)
                ->where('companies.client_id', $companyId)
                ->delete();

            return response()->json([
                'message' => 'Venta eliminada correctamente',
                'data' => "Venta ID: $saleId eliminada",
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la venta: ' . $e->getMessage()], 500);
        }
    }
}