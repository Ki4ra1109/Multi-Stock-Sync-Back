<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetHistoryPendientController
{
    public function getHistoryPendient(Request $request,$clientId)
    {
        try {
            $clientId = $request->input('client_id');
            $dateStart = $request->input('date_start');
            $allSale = $request->input('all_sale');
            $company = Company::where('client_id', $clientId)->first();

            if (!$company) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Compañía no encontrada',
                    'company_client_id' => $clientId
                ], 404);
            }

            // Obtener ventas pendientes usando Eloquent relationships
            $sales = Sale::with([
                'warehouse:id,name', // Solo cargar id y name del warehouse
                'productSales.stockWarehouse:id,title' // Cargar stockWarehouse con title
            ])
                ->whereHas('warehouse.company', function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                })->when($clientId, function ($query, $clientId) {
                    return $query->where('client_id', $clientId);
                })
                ->when($dateStart, function ($query, $dateStart) {
                    return $query->whereDate('created_at', '>=', $dateStart);
                })
                ->when($allSale, function ($query, $allSale) {
                    return $query->where('amount_total_products', $allSale);
                })
                ->where('status_sale', 'Pendiente')
                ->get();

            // Formatear la respuesta de forma anidada
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
                            'product_id' => $productSale->product_id,
                            'product_name' => $productSale->stockWarehouse->title ?? 'Producto no disponible',
                            'quantity' => $productSale->cantidad,
                            'price_unit' => $productSale->precio_unidad,
                            'subtotal' => $productSale->precio_total,
                        ];
                    })
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Ventas pendientes obtenidas correctamente',
                'data' => $formattedSales,
                'count' => $sales->count(),
            ], 200);
        } catch (\Exception $e) {
            error_log('Error en getHistoryPendient: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las ventas pendientes',
                'error' => $e->getMessage(),
                'debug' => [
                    'client_id' => $clientId,
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            ], 500);
        }
    }
}
