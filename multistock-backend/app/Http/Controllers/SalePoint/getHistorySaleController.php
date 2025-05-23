<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use App\Models\Company;
use App\Models\ProductSale;
use App\Models\StockWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetHistorySaleController
{
    public function getHistorySale(Request $request, $companyId)
    {


        try {
            // Validar los datos de entrada
            $clientId = $request->input('client_id');
            $dateStart = $request->input('date_start');
            $allSale = $request->input('all_sale');
            $status_sale = $request->input('status_sale');

            // Primero verificamos que la compañía existe
            $company = Company::where('client_id', $companyId)->first();

            if (!$company) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Compañía no encontrada',
                    'company_client_id' => $companyId
                ], 404);
            }

            // Obtenemos las ventas con todas las relaciones necesarias
            $sales = Sale::with([
                'warehouse:id,name', // Solo cargamos id y name del warehouse
                 'productSales.stockWarehouse:id,title'
            ])
            ->whereHas('warehouse.company', function ($query) use ($companyId) {
                $query->where('client_id', $companyId);
            })
            ->when($clientId, function ($query, $clientId) {
                return $query->where('client_id', $clientId);
            })
            ->when($dateStart, function ($query, $dateStart) {
                return $query->whereDate('created_at', '>=', $dateStart);
            })
            ->when($allSale, function ($query, $allSale) {
                return $query->where('amount_total_products', $allSale);
            })
            ->when($status_sale, function ($query, $status_sale) {
                return $query->where('status_sale', $status_sale);
            })
            ->get();

            // Debug adicional
            error_log("Ventas encontradas: " . $sales->count());
            if ($sales->first()) {
                error_log('Primera venta ID: ' . $sales->first()->id);
            }

            $formattedSales = $sales->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'client_id' => $sale->client_id,
                    'warehouse_id' => $sale->warehouse_id,
                    'warehouse_name' => $sale->warehouse->name ?? 'N/A',
                    'amount_total_products' => $sale->amount_total_products,
                    'status_sale' => $sale->status_sale,
                    'created_at' => $sale->created_at->format('Y-m-d'),
                    'updated_at' => $sale->updated_at->format('Y-m-d'),
                    'products' => $sale->productSales->map(function ($productSale) {
                        return [
                            'product_name' => $productSale->stockWarehouse->title ?? 'N/A',
                            'product_id' => $productSale->product_id,
                            'quantity' => $productSale->cantidad,
                            'price_unit' => $productSale->precio_unidad,
                            'subtotal' => $productSale->precio_total,
                        ];
                    })
                ];
            });
            return response()->json([
                'status' => 'success',
                'message' => 'Historial de ventas obtenido correctamente',
                'data' => $formattedSales,
                'count' => $sales->count(),
            ], 200);
        } catch (\Exception $e) {

            error_log('Error en getHistorySale: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el historial de ventas',
                'error' => $e->getMessage(),
                'debug' => [
                    'company_id' => $companyId,
                    'client_id' => $clientId,
                    'date_start' => $dateStart,
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            ], 500);
        }
    }
}
