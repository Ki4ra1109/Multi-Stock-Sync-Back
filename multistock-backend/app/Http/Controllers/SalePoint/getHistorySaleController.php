<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class getHistorySaleController{

    public function getHistorySale(Request $request, $companyId){
        try{
            // Validar los datos de entrada
            $clientId = $request->input('client_id');
            $dateStart = $request->input('date_start');
            $allSale = $request->input('all_sale');
            $status_sale = $request->input('status_sale');
            
            $historySale = DB::Table("sale")
                ->join('warehouses', 'sale.warehouse_id', '=', 'warehouses.id')
                ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
                ->select(
                    'sale.*',
                    'warehouses.name as warehouse_name'
                )
                ->when(!is_null($clientId), function ($query) use ($clientId) {
                    return $query->where('companies.client_id', $clientId);
                })
                ->when(!is_null($dateStart), function ($query) use ($dateStart) {
                    return $query->whereDate('sale.created_at', '>=', $dateStart);
                })
                ->when(!is_null($allSale), function ($query) use ($allSale) {
                    return $query->where('sale.amount_total_products', $allSale);
                })
                ->when(!is_null($status_sale), function ($query) use ($status_sale) {
                    return $query->where('sale.status_sale', $status_sale);
                })
                ->where('companies.client_id', $companyId)
                ->get();

            return response()->json([
                'message' => 'Historial de ventas obtenido correctamente',
                'data' => $historySale,
            ], 200);
        }
        catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el historial de ventas: ' . $e->getMessage()], 500);
        }
    }

}