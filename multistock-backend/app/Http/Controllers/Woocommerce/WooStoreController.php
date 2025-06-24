<?php

namespace App\Http\Controllers\Woocommerce;

use App\Models\WooStore;
use Automattic\WooCommerce\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

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

            // Obtener parámetros de paginación con valores por defecto
            $page = $request->input('page', 1); // Página 1 por defecto
            $perPage = $request->input('per_page', 50); // 10 items por página por defecto
            

            $page = max(1, (int)$page);
            $perPage = max(1, (int)$perPage);

            // Obtener los productos de la página solicitada
            $products = $woocommerce->get('products', [
                'per_page' => $perPage,
                'page' => $page
            ]);

            $formattedProducts = [];
            foreach ($products as $product) {
                $formattedProducts[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'regular_price' => $product->regular_price,
                    'stock_quantity' => $product->stock_quantity ?? 'N/A',
                    'permalink' => $product->permalink,
                    'sku' => $product->sku,
                    'weight' => $product->weight,
                    'dimensions' => $product->dimensions,
                    'status' => $product->status,
                    'images' => $product->images,
                    'attributes' => $product->attributes,

                ];
            }

            $headers = $woocommerce->http->getResponse()->getHeaders();
            $totalProducts = $headers['X-WP-Total'] ?? 0;
            $totalPages = $headers['X-WP-TotalPages'] ?? 1;

            return response()->json([
                'user' => $user->email,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_products' => (int)$totalProducts,
                'total_pages' => (int)$totalPages,
                'products' => $formattedProducts
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

    public function testConnection($storeId)
    {
        try {
            $woocommerce = $this->connect($storeId);
            $woocommerce->get('products', ['per_page' => 1]);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error("Falló test de conexión Woo ID $storeId: " . $e->getMessage());

            return response()->json([
                'status' => 'fail',
                'message' => $e->getMessage(),
                'exception' => get_class($e)
            ], 500);
        }
    }

    
    public function getStores(Request $request)
    {
        $stores = WooStore::all(['id', 'name']);

        return response()->json([
            'stores' => $stores
        ]);
    }
}