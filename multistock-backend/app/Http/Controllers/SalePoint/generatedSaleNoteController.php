<?php

namespace App\Http\Controllers\SalePoint;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\ProductSale;
use App\Models\StockWarehouse;
use App\Enums\StateVentaEnums;



class GeneratedSaleNoteController

{

    public function generatedSaleNote(Request $request, $status)
    {


        if (!StateVentaEnums::tryFrom($status)) {
            return response()->json(['message' => 'Estado no válido',
        'status'=>'error'], 422);
        }
        DB::beginTransaction();

        try {
            // Validar los datos de entrada para boleta
            $validateData = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'client_id' => 'required|integer|exists:clientes,id',
                'products' =>'required|array',
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

            // Crear la venta
            $ventaData = $validateData;
            unset($ventaData['products']);
            $ventaData['status_sale'] = $status;
            $venta = Sale::create($ventaData);

            // Guardar cada producto de la venta
            foreach ($validateData['products'] as $productData) {
                // Verificar existencia y stock del producto
                $product = StockWarehouse::findOrFail($productData['id']);
                $stock = StockWarehouse::where('id', $productData['id'])
                    ->where('warehouse_id', $validateData['warehouse_id'])
                    ->firstOrFail();
                if ($stock->available_quantity < $productData['cantidad']) {
                    throw new \Exception("Stock insuficiente para el producto ID {$productData['id']}. Disponible: {$stock->available_quantity}, Solicitado: {$productData['quantity']}");
                }
                ProductSale::create([
                    'venta_id' => $venta->id,
                    'product_id' => $productData['id'],
                    'cantidad' => $productData['cantidad'],
                    'precio_unidad' => $productData['precioUnitario'],
                    'precio_total' => $productData['total'],
                ]);
                //si la venta no se finaliza no restan los stocks
                if ($venta->status_sale == 'Pendiente') {
                    continue;
                }
                $product->decrement('available_quantity', $productData['cantidad']);
            }

            DB::commit();
            $data =[
                'id' => $venta->id,
                'client_id' => $venta->client_id,
                'warehouse_id' => $venta->warehouse_id,
                'warehouse_name' => $venta->warehouse->name ?? 'N/A',
                'amount_total_products' => $venta->amount_total_products,
                'status_sale' => $venta->status_sale,
                'created_at' => $venta->created_at->format('Y-m-d'),
                'updated_at' => $venta->updated_at->format('Y-m-d'),
            ];

            return response()->json([
                'message' => 'Venta creada exitosamente.',
                'data' => $data,
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
