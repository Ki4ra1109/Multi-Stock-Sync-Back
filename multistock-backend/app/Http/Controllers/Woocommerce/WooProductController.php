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
                'regular_price' => $update->regular_price,
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

}
