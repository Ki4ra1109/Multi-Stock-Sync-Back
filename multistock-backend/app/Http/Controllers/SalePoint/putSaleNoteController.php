<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use App\Models\ProductSale;
use App\Models\StockWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\StateVentaEnums;
class PutSaleNoteController
{
    public function putSaleNote(Request $request, $saleId, $status)
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

            // Validar datos de entrada
            $validatedData = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'client_id' => 'required|integer|exists:clientes,id',
                'products' => 'required|array',
                'products.*.id' =>'required|integer|exists:stock_warehouses,id',
                'products.*.nombre'=>'required|string|max:255',
                'products.*.cantidad' => 'required|integer|min:1',
                'products.*.precioUnitario' => 'required|numeric|min:0',
                'products.*.total' => 'required|numeric|min:0',
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
            $stock = StockWarehouse::where('id', $productData['id'])
                ->where('warehouse_id', $sale->warehouse_id)
                ->firstOrFail();

            if ($stock->available_quantity < $productData['cantidad']) {
                throw new \Exception("Stock insuficiente para el producto ID {$productData['id']}. Disponible: {$stock->available_quantity}, Solicitado: {$productData['cantidad']}");
            }

            // Crear registro de producto en la venta
            ProductSale::create([
                'venta_id' => $sale->id,
                'product_id' => $productData['id'],
                'cantidad' => $productData['cantidad'],
                'precio_unidad' => $productData['precioUnitario'],
                'precio_total' => $productData['total'],
            ]);

            // Restar stock si la venta no está pendiente
            if ($sale->status_sale !== 'Pendiente') {
                $stock->decrement('available_quantity', $productData['cantidad']);
            }
        }
    }
}
