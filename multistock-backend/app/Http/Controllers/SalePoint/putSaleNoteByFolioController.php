<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class putSaleNoteByFolioController
{
    public function putSaleNoteByFolio(Request $request, $companyId, $folio)
    {
        try {
            // Actualizar la venta
            $sale = Sale::join('warehouses', 'sales.warehouse_id', '=', 'warehouses.id')
                ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
                ->select('sales.*', 'warehouses.name as warehouse_name')
                ->where('sales.id', $folio)
                ->where('companies.client_id', $companyId)
                ->first();

            if (!$sale) {
                return response()->json(['message' => 'No se encontró la venta.'], 404);
            }

            if ($sale[0]->status_sale === 'Emitido') {
                return response()->json(['message' => 'La venta ya fue emitida.'], 400);
            }

            if ($sale[0]->status_sale !== 'Finalizada') {
                return response()->json(['message' => 'Solo se pueden emitir ventas con estado "Finalizado".'], 400);
            }

            $validate = $request->validate([
                'type_emission' => 'required|string|max:255',
                'observation' => 'nullable|string|max:255',
                'name_companies' => 'nullable|string|max:255',
            ]);

            if ($validate["type_emission"] === "Boleta") {
                $sale->status_sale = "Emitido";
                $sale->type_emission = $validate["type_emission"];
                $sale->observation = $validate["observation"];
                $sale->save();
                $uploadSale = Sale::where('id', $folio)->first();
                return response()->json([
                    'message' => 'Documento emitido con éxito.',
                    'data' => $updatedSale // Retornar los datos actualizados de la venta
                ], 200); // Status 200 OK
            } 
            else if($validate["type_emission"] === "Factura"){
                $sale->status_sale = "Emitido";
                $sale->type_emission = $validate["type_emission"];
                $sale->observation = $validate["observation"];
                $sale->name_companies = $validate["name_companies"];
                $sale->save();
                $uploadSale = Sale::where('id', $folio)->first();

                $client = Client::where('id', $sale->client_id)->first();

                $clientData = [
                    'name' => $client->nombres,
                    'rut' => $client->rut,
                    'razon_social' => $client->razon_social,
                    'giro' => $client->giro,
                ];

                return response()->json([
                    'message' => 'Documento emitido con éxito.',
                    'data' => $updatedSale, // Retornar los datos actualizados de la venta
                    'client' => $clientData,
                ], 200); // Status 200 OK

            } 

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la venta.', 'error' => $e->getMessage()], 500);
        }
    }
}