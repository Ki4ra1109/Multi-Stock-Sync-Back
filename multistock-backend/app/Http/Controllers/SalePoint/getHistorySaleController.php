<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class getHistorySaleController{

    public function getHistorySale(Request $request){
        try{
            // Validar los datos de entrada
            $clientId = (int) $request->input('client_id', null);
            $dateStart = $request->input('date_start', null);
            $allSale = $request->input('allSale', null);
            $state = $request->input('state', null);

            $historySale = Sale::query()
                ->when(!is_null($clientId), function ($query) use ($clientId) {
                    return $query->where('client_id', $clientId);
                })
                ->when(!is_null($dateStart), function ($query) use ($dateStart) {
                    return $query->whereDate('created_at', '>=', $dateStart);
                })
                ->when(!is_null($allSale), function ($query) use ($allSale) {
                    return $query->where('amount_total_products', $allSale);
                })
                ->when(!is_null($state), function ($query) use ($state) {
                    return $query->where('state_sale', $state);
                })
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