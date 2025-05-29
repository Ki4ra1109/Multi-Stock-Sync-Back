<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use App\Models\ProductSale;
use App\Models\StockWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\StateVentaEnums;

class GetHistorySalePatchStatusController
{
    public function getHistorySalePatchStatus($saleId, $status)
    {
        if (!StateVentaEnums::tryFrom($status)) {
            return response()->json([
                'message' => 'Estado no válido',
                'status' => 'error'
            ], 422);
        }

        try {
            $sale = Sale::findOrFail($saleId);

            $sale->status_sale = $status;
            $sale->save();

            return response()->json([
                'message' => 'Venta actualizada exitosamente.',
                'data' => $sale->id,
                'status' => 'success'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Recurso no encontrado: ' . $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
