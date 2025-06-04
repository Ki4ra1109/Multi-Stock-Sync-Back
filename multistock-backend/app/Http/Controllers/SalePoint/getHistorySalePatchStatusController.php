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
            return response()->json(['message' => 'Estado no válido',
        'status'=>'error'], 422);
        }
        DB::beginTransaction();

        try {
            // Validar que la venta exista
            $saleId = (int) $saleId;
            $sale = Sale::findOrFail($saleId);

            $sale->update([
                'status_sale' => $status
            ]);

            // Sincronizar productos de la venta

            DB::commit();

            // Cargar relaciones para la respuesta


            return response()->json([
                'message' => 'Venta actualizada exitosamente.',
                'data' => $sale->id,
                'status' => 'success'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Recurso no encontrado: ' . $e->getMessage()
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la venta',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Only for development
            ], 500);
        }
    }



}
