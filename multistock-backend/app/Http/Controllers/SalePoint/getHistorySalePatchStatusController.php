<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class getHistorySalePatchStatusController{

    public function getHistorySalePatchStatus(Request $request, $saleId, $status){
        try{
            $type_emission = $request->input('type_emission');

            // Validar el tipo de emisión
            if ($type_emission === 'boleta') {
                //Validar los datos de entrada para boleta
                $validateData = $request->validate([
                    'warehouse_id' => 'required|integer|exists:warehouses,id',
                    'client_id' => 'required|integer|exists:clientes,id',
                    'products' => 'required|array',
                    'amount_total_products' => 'required|integer',
                    'price_subtotal' => 'required|integer',
                    'price_final' => 'required|integer',
                    'type_emission' => 'required|string|max:255',
                    'observation' => 'nullable|string|max:255',
                ]);

                $validateData["products"] = json_encode($validateData["products"]);
                $validateData["status_sale"] = $status;

                $actualizarVenta = Sale::where('id', $saleId)->update($validateData);
                $vista = Sale::where('id', $saleId)->first();   

            } else if ($type_emission === 'factura') {
                //Validar los datos de entrada para factura
                $validateData = $request->validate([
                    'warehouse_id' => 'required|integer|exists:warehouses,id',
                    'client_id' => 'required|integer|exists:clientes,id',
                    'products' => 'required|array',
                    'amount_total_products' => 'required|integer',
                    'price_subtotal' => 'required|integer',
                    'price_final' => 'required|integer',
                    'type_emission' => 'required|string|max:255',
                    'observation' => 'nullable|string|max:255',
                    'name_companies' => 'required|string|max:255',
                ]);

                $validateData["products"] = json_encode($validateData["products"]);
                $validateData["status_sale"] = $status;

                $actualizarVenta = Sale::where('id', $saleId)->update($validateData);

                $vista = Sale::where('id', $saleId)->first();

            } else {
                // Respuesta de error si el tipo de emisión no es válido
                return response()->json(['message' => 'Tipo de emisión no válido.'], 422);
            }

            // Respuesta exitosa
            return response()->json([
                'message' => 'La venta ha sido creado.', 
                'data' => $vista
            ], 201);
        }
        catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la venta.', 'error' => $e->getMessage()], 500);
        }
    }

}