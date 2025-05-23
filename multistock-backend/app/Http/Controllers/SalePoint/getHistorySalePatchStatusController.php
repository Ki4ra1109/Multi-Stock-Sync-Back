<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use App\Models\ProductSale;
use App\Models\StockWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetHistorySalePatchStatusController
{
    public function getHistorySalePatchStatus(Request $request, $saleId, $status)
    {
        DB::beginTransaction();

        try {
            // Validar que la venta exista
            $saleId = (int) $saleId;
            $sale = Sale::findOrFail($saleId);

            // Validar datos de entrada
            $validatedData = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'client_id' => 'required|integer|exists:clientes,id',
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:stock_warehouses,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.price_unit' => 'required|numeric|min:0',
                'products.*.subtotal' => 'required|numeric|min:0',
                'amount_total_products' => 'required|integer',
                'price_subtotal' => 'required|numeric',
                'price_final' => 'required|numeric',
                'type_emission' => 'nullable|string|max:255',
                'observation' => 'nullable|string|max:255',
                'name_companies' => 'nullable|string|max:255',
            ]);

            // Actualizar la venta
            $sale->update([
                'warehouse_id' => $validatedData['warehouse_id'],
                'client_id' => $validatedData['client_id'],
                'amount_total_products' => $validatedData['amount_total_products'],
                'price_subtotal' => $validatedData['price_subtotal'],
                'price_final' => $validatedData['price_final'],
                'type_emission' => $validatedData['type_emission'] ?? null,
                'observation' => $validatedData['observation'] ?? null,
                'name_companies' => $validatedData['name_companies'] ?? null,
                'status_sale' => $status
            ]);

            // Sincronizar productos de la venta
            $this->syncProducts($sale, $validatedData['products']);

            DB::commit();

            // Cargar relaciones para la respuesta
            $sale->load(['productSales.stockWarehouse', 'warehouse']);

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

    protected function syncProducts(Sale $sale, array $products)
    {
        // Eliminar productos antiguos
        ProductSale::where('venta_id', $sale->id)->delete();

        foreach ($products as $productData) {
            // Verificar stock disponible
            $stock = StockWarehouse::where('id', $productData['product_id'])
                ->where('warehouse_id', $sale->warehouse_id)
                ->firstOrFail();

            if ($stock->available_quantity < $productData['quantity']) {
                throw new \Exception("Stock insuficiente para el producto ID {$productData['product_id']}. Disponible: {$stock->available_quantity}, Solicitado: {$productData['quantity']}");
            }

            // Crear registro de producto en la venta
            ProductSale::create([
                'venta_id' => $sale->id,
                'product_id' => $productData['product_id'],
                'cantidad' => $productData['quantity'],
                'precio_unidad' => $productData['price_unit'],
                'precio_total' => $productData['subtotal'],
            ]);

            // Restar stock si la venta no está pendiente
            if ($sale->status_sale !== 'Pendiente') {
                $stock->decrement('available_quantity', $productData['quantity']);
            }
        }
    }
}
