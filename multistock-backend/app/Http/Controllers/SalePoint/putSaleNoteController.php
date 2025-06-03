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
        // Validar estado primero (fuera de transacción)
        if (!StateVentaEnums::tryFrom($status)) {
            return response()->json([
                'message' => 'Estado no válido',
                'status' => 'error'
            ], 422);
        }

        // Validar datos de entrada
        $validatedData = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'client_id' => 'required|integer|exists:clientes,id',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|integer|exists:stock_warehouses,id',
            'products.*.nombre' => 'required|string|max:255',
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

        DB::beginTransaction();

        try {
            // Bloquear la venta para evitar condiciones de carrera
            $sale = Sale::lockForUpdate()->findOrFail($saleId);

            // Actualizar campos básicos de la venta
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

            // Sincronizar productos de forma optimizada
            $this->syncProductsOptimized($sale, $validatedData['products']);

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
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }


    protected function syncProductsOptimized(Sale $sale, array $products)
    {
        //  Eliminar todos los productos antiguos en una sola operación
        ProductSale::where('venta_id', $sale->id)->delete();

        // . Obtener IDs de productos para precargar stock
        $productIds = array_column($products, 'id');

        //  Precargar todo el stock necesario en una sola consulta
        $stocks = StockWarehouse::where('warehouse_id', $sale->warehouse_id)
            ->whereIn('id', $productIds)
            ->get(['id', 'available_quantity'])
            ->keyBy('id');

        //  Preparar datos para inserción masiva y verificación de stock
        $productSalesToInsert = [];
        $stocksToUpdate = [];
        $now = now();

        foreach ($products as $product) {
            $stock = $stocks[$product['id']] ?? null;

            // Verificar existencia y stock
            if (!$stock) {
                throw new \Exception("Producto ID {$product['id']} no encontrado en el almacén");
            }

            if ($stock->available_quantity < $product['cantidad']) {
                throw new \Exception(
                    "Stock insuficiente para producto ID {$product['id']}. " .
                    "Disponible: {$stock->available_quantity}, Solicitado: {$product['cantidad']}"
                );
            }

            // Preparar datos para inserción masiva
            $productSalesToInsert[] = [
                'venta_id' => $sale->id,
                'product_id' => $product['id'],
                'cantidad' => $product['cantidad'],
                'precio_unidad' => $product['precioUnitario'],
                'precio_total' => $product['total'],
                'created_at' => $now,
                'updated_at' => $now
            ];

            // Preparar actualización de stock si no es Pendiente
            if ($sale->status_sale !== 'Pendiente') {
                $stocksToUpdate[$product['id']] = $product['cantidad'];
            }
        }

        //  Inserción masiva de productos
        if (!empty($productSalesToInsert)) {
            ProductSale::insert($productSalesToInsert);
        }

        // stock
        if (!empty($stocksToUpdate) && $sale->status_sale !== 'Pendiente') {
            foreach ($stocksToUpdate as $productId => $quantity) {
                StockWarehouse::where('id', $productId)
                    ->decrement('available_quantity', $quantity);
            }
        }
    }
}
