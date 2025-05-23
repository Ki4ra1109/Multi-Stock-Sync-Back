<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\ProductSale;
use App\Models\StockWarehouse;


class GeneratedSaleNoteController
{
    public function generatedSaleNote(Request $request, $status)
    {
        DB::beginTransaction();

        try {
            // Validar los datos de entrada para boleta
            $validateData = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'client_id' => 'required|integer|exists:clientes,id',
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:stock_warehouses,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.price_unit' => 'required|numeric|min:0',
                'products.*.discount' => 'nullable|numeric|min:0',
                'products.*.subtotal' => 'required|numeric|min:0',
                'amount_total_products' => 'required|integer',
                'price_subtotal' => 'required|numeric',
                'price_final' => 'required|numeric',
                'type_emission' => 'nullable|string|max:255',
                'observation' => 'nullable|string|max:255',
                'name_companies' => 'nullable|string|max:255',
            ]);

            // Crear la venta
            $ventaData = $validateData;
            unset($ventaData['products']);
            $ventaData['status_sale'] = $status;
            $venta = Sale::create($ventaData);
            // Guardar cada producto de la venta
            foreach ($validateData['products'] as $productData) {
                // Verificar existencia y stock del producto
                $product = StockWarehouse::findOrFail($productData['product_id']);
                $stock = StockWarehouse::where('id', $productData['product_id'])
                    ->where('warehouse_id', $validateData['warehouse_id'])
                    ->firstOrFail();
                if ($stock->available_quantity < $productData['quantity']) {
                    throw new \Exception("Stock insuficiente para el producto ID {$productData['product_id']}. Disponible: {$stock->available_quantity}, Solicitado: {$productData['quantity']}");
                }
                ProductSale::create([
                    'venta_id' => $venta->id,
                    'product_id' => $productData['product_id'],
                    'cantidad' => $productData['quantity'],
                    'precio_unidad' => $productData['price_unit'],
                    'precio_total' => $productData['subtotal'],
                ]);
                //si la venta no se finaliza no restan los stocks
                if ($venta->status_sale == 'Pendiente') {
                    continue;
                }
                $product->decrement('available_quantity', $productData['quantity']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Venta creada exitosamente.',
                'data' => $venta->id,
                'status' => 'success'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
