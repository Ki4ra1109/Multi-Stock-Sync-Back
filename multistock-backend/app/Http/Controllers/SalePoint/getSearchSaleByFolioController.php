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

            $sale = Sale::with([
                'warehouse:id,name',
                'productSales.stockWarehouse:id,title',
            ])
                ->whereHas('warehouse.company', function ($query) use ($companyId) {
                    $query->where('client_id', $companyId);
                })
                ->where('id', $folio)
                ->first();
            if (!$sale) {
                return response()->json([
                    'message' => 'No se encontró la venta con el folio proporcionado',
                    'data' => null,
                ], 404);
            }
            $responseFormated = [

                'id' => $sale->id,
                'client_id' => $sale->client_id,
                'warehouse_id' => $sale->warehouse_id,
                'warehouse_name' => $sale->warehouse->name ?? 'N/A',
                'amount_total_products' => $sale->amount_total_products,
                'status_sale' => $sale->status_sale,
                'type_emission' => $sale->type_emission,
                'price_final' => $sale->price_final,
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
                })->toArray(),


            ];


            return response()->json([
                'status' => 'success',
                'message' => 'Venta obtenida correctamente',
                'data' => $responseFormated,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al obtener la venta: ' . $e->getMessage()], 500);
        }
    }
}
