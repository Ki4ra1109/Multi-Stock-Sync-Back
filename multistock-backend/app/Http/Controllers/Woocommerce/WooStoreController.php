<?php

namespace App\Http\Controllers\Woocommerce;

use App\Models\WooStore;
use Automattic\WooCommerce\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class WooStoreController extends Controller
{
   
    public function __construct()
    {
        set_time_limit(1000);
        $this->middleware('auth:sanctum'); 
    }

    public function connect($storeId)
    {
        $store = WooStore::findOrFail($storeId);

        if (!$store->active) {
            return response()->json(['error' => 'Tienda desactivada'], 403);
        }

        $woocommerce = new Client(
            $store->store_url,
            $store->consumer_key,
            $store->consumer_secret,
            [
                'version' => 'wc/v3',
                'verify_ssl' => false 
            ]
        );

        return $woocommerce;
    }

   public function getProductsWooCommerce(Request $request, $storeId)
    {
        $user = $request->user();

        try {
            $woocommerce = $this->connect($storeId);

            $allProducts = [];
            $page = 1;
            $perPage = 100;
            $count = 0; // contador de productos

            do {
                $products = $woocommerce->get('products', [
                    'per_page' => $perPage,
                    'page' => $page
                ]);

                foreach ($products as $product) {
                    $allProducts[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'stock_quantity' => $product->stock_quantity ?? 'N/A',
                        'permalink' => $product->permalink,
                        'sku' => $product->sku,
                    ];
                    $count++; // incrementar contador
                }

                $page++;
            } while (count($products) === $perPage);

            return response()->json([
                'user' => $user->email,
                'total_products' => $count,
                'products' => $allProducts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al conectar: ',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ], 500);
        }
    }
        public function storeWoocommerce(Request $request)
        {
            $user = $request->user();

            // Validación
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'store_url' => 'required|url|unique:woo_stores,store_url',
                'consumer_key' => 'required|string',
                'consumer_secret' => 'required|string',
                'active' => 'boolean'
            ]);

            // Crear tienda
            $wooStore = WooStore::create([
                ...$validated,
                'user_id' => $user->id // solo si tu tabla tiene esta columna
            ]);

            return response()->json([
                'message' => 'Tienda registrada correctamente por ' . $user->name,
                'store' => $wooStore
            ], 201);
        }


}
