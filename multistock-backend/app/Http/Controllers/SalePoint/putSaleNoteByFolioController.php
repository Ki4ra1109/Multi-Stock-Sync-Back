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
            // Buscar la venta con joins y validaciones de compañía
            $sale = Sale::join('warehouses', 'sale.warehouse_id', '=', 'warehouses.id')
                ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
                ->select('sale.*', 'warehouses.name as warehouse_name')
                ->where('sale.id', $folio)
                ->where('companies.client_id', $companyId)
                ->first();

            if (!$sale) {
                return response()->json(['message' => 'No se encontró la venta.'], 404);
            }

            // Verificar estado actual de la venta
            if ($sale->status_sale === 'emitido') {
                return response()->json(['message' => 'La venta ya fue emitida.'], 400);
            }

            // Validar los datos del request
            $validated = $request->validate([
                'type_emission' => 'required|string|max:255',
                'observation' => 'nullable|string|max:255',
                'name_companies' => 'nullable|string|max:255',
            ]);

            // Actualizar venta según el tipo de emisión
            $sale->status_sale = 'emitido';
            $sale->type_emission = $validated['type_emission'];
            $sale->observation = $validated['observation'] ?? null;

            if ($validated['type_emission'] === 'factura') {
                $sale->name_companies = $validated['name_companies'] ?? null;
            }

            $sale->save();

            // Recargar la venta actualizada
            $updatedSale = Sale::find($folio);

            // Si es factura, obtener datos del cliente
            if ($validated['type_emission'] === 'factura') {
                $client = Client::find($sale->client_id);
                $clientData = [
                    'name' => $client->nombres ?? null,
                    'rut' => $client->rut ?? null,
                    'razon_social' => $client->razon_social ?? null,
                    'giro' => $client->giro ?? null,
                ];
                return response()->json([
                    'message' => 'Documento emitido con éxito.',
                    'data' => $updatedSale,
                    'client' => $clientData,
                ], 200);
            }

            // Si es boleta, solo retornar venta
            return response()->json([
                'message' => 'Documento emitido con éxito.',
                'data' => $updatedSale,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}