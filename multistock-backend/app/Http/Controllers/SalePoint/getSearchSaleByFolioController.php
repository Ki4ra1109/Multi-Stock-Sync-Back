<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


class getSearchSaleByFolioController
{
    public function getSearchSaleByFolio(Request $request, $companyId)
    {
        try {
            // Validar los datos de entrada
            $folio = $request->input('folio');
            $clientId = $request->input('client_id');
            
            $sale = DB::table("sale")
                ->join('warehouses', 'sale.warehouse_id', '=', 'warehouses.id')
                ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
                ->select(
                    'sale.*',
                    'warehouses.name as warehouse_name'
                )
                ->when(!is_null($folio), function ($query) use ($folio) {
                    return $query->where('sale.id', $folio);
                })
                ->where('companies.client_id', $companyId)
                ->first();
            
            if (!$sale) {
                return response()->json([
                    'message' => 'No se encontró la venta con el folio proporcionado',
                    'data' => null,
                ], 404);
            }
            
            return response()->json([
                'message' => 'Venta obtenida correctamente',
                'data' => $sale,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener la venta: ' . $e->getMessage()], 500);
        }
    }
}