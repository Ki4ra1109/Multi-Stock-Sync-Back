<?php

namespace App\Http\Controllers\Woocommerce;

use App\Models\WooStore;
use Automattic\WooCommerce\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WooProductController extends Controller
{
    public function __construct()
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        $this->middleware('auth:sanctum');
    }

    private function connect($storeId)
    {
        $store = WooStore::findOrFail($storeId);

        if (!$store->active) {
            throw new \Exception('Tienda desactivada');
        }

        return new Client(
            $store->store_url,
            $store->consumer_key,
            $store->consumer_secret,
            [
                'version' => 'wc/v3',
                'verify_ssl' => false
            ]
        );
    }

    public function updateProduct(Request $request, $storeId, $productId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $data = $request->all();

            if (empty($data)) {
                return response()->json([
                    'message' => 'Debes enviar al menos un campo a modificar.',
                    'status' => 'error'
                ], 422);
            }

            $updated = $woocommerce->put("products/{$productId}", $data);

            $filtered = [
                'id' => $updated->id,
                'name' => $updated->name,
                'regular_price' => $updated->regular_price,
                'stock_quantity' => $updated->stock_quantity ?? 'N/A',
                'permalink' => $updated->permalink,
                'sku' => $updated->sku,
                'weight' => $updated->weight,
                'dimensions' => $updated->dimensions,
                'status' => $updated->status,
            ];

            return response()->json([
                'message' => 'Producto actualizado correctamente.',
                'updated_product' => $filtered,
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createProduct(Request $request, $storeId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $data = $request->all();

            // Validación de campos requeridos
            if (empty($data['name'])) {
                return response()->json([
                    'message' => 'El nombre del producto es requerido.',
                    'status' => 'error'
                ], 422);
            }

            // Campos por defecto para un nuevo producto
            $productData = array_merge([
                'type' => 'simple',
                'status' => 'publish',
                'featured' => false,
                'catalog_visibility' => 'visible',
                'manage_stock' => false,
            ], $data);

            $created = $woocommerce->post("products", $productData);

            $filtered = [
                'id' => $created->id,
                'name' => $created->name,
                'regular_price' => $created->regular_price,
                'stock_quantity' => $created->stock_quantity ?? 'N/A',
                'permalink' => $created->permalink,
                'sku' => $created->sku,
                'weight' => $created->weight,
                'dimensions' => $created->dimensions,
                'status' => $created->status,
            ];

            return response()->json([
                'message' => 'Producto creado correctamente.',
                'created_product' => $filtered,
                'status' => 'success'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
