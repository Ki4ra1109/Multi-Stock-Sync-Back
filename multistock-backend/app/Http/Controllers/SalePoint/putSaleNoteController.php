<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use App\Models\ProductSale;
use App\Models\StockWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class putSaleNoteController
{
    public function putSaleNote(Request $request, $saleId, $status)
    {
        DB::beginTransaction();

        try {
            // Validar que la venta exista
            $sale = Sale::findOrFail($saleId);

            // Validar datos de entrada
            $validatedData = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'client_id' => 'required|integer|exists:clientes,id',
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:stockWarehouse,id',
                'products.*.cantidad' => 'required|integer|min:1',
                'products.*.precio_unidad' => 'required|numeric|min:0',
                'products.*.precio_total' => 'required|numeric|min:0',
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
            $this->syncProducts($sale->id, $validatedData['products'],$status);

            DB::commit();

            // Cargar relaciones para la respuesta
            $sale->load(['productSales.product', 'warehouse']);

            return response()->json([
                'message' => 'Venta actualizada exitosamente.',
                'data' => $sale,
                'products' => $sale->productSales
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
                'message' => 'Venta no encontrada'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function syncProducts($saleId, array $products,$status)
    {
        // Eliminar productos antiguos
        ProductSale::where('venta_id', $saleId)->delete();

        // Crear nuevos registros de productos
        foreach ($products as $product) {
            ProductSale::create([
                'venta_id' => $saleId,
                'product_id' => $product['product_id'],
                'cantidad' => $product['cantidad'],
                'precio_unidad' => $product['precio_unidad'],
                'precio_total' => $product['precio_total']
            ]);
        }
    }
}
