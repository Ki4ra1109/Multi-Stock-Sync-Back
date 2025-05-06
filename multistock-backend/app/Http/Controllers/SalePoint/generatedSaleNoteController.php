<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class generatedSaleNoteController{

    public function generatedSaleNote(Request $request){

        try{

            $warehouseId = (int) $request->input('warehouse_id');
            $clientId = (int) $request->input('cliente_id');
            $products = $request->input('products');
            $amount_total_product = (int) $request->input('amount');
            $price_subtotal = (int) $request->input('price_subtotal');
            $price_total = (int) $request->input('price_total');
            $name_companies = $request->input('name_companies', null);
            $type_emission = $request->input('type_emission');
            $observation = $request->input('observation');

            // Validar el tipo de emisión
            if ($type_emission === 'boleta') {
                //Validar los datos de entrada para boleta
                $validateData = $request->validate([
                    'warehouse_id' => 'required|integer|exists:warehouses,id',
                    'cliente_id' => 'required|integer|exists:clients,id',
                    'products' => 'required|array',
                    'amount' => 'required|integer',
                    'price_subtotal' => 'required|integer',
                    'price_total' => 'required|integer',
                    'type_emission' => 'required|string|max:255',
                    'observation' => 'nullable|string|max:255',
                ]);
                $venta = Sale::create($validateData);   

            } else if ($type_emission === 'factura') {
                //Validar los datos de entrada para factura
                $validateData = $request->validate([
                    'warehouse_id' => 'required|integer|exists:warehouses,id',
                    'cliente_id' => 'required|integer|exists:clients,id',
                    'products' => 'required|array',
                    'amount' => 'required|integer',
                    'price_subtotal' => 'required|integer',
                    'price_total' => 'required|integer',
                    'type_emission' => 'required|string|max:255',
                    'observation' => 'nullable|string|max:255',
                    'name_companies' => 'required|string|max:255',
                ]);
                $venta = Sale::create($validateData);

            } else {
                // Respuesta de error si el tipo de emisión no es válido
                return response()->json(['message' => 'Tipo de emisión no válido.'], 422);
            }

            // Respuesta exitosa
            return response()->json(['message' => 'La venta ha sido creado.', 'data' => $venta], 201);
        }
        catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la venta.', 'error' => $e->getMessage()], 500);
        }
    }

}