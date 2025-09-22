<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use App\Models\WooStore;
use Automattic\WooCommerce\Client as WooCommerceClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\Utils;
use App\Jobs\SyncProductsJob;

class SyncWithWooCommerceController extends Controller
{
    private $batchSize = 20; // Reducir tamaño de lote para mejor rendimiento
    private $maxProductsPerBatch = 10; // Máximo productos por lote de sincronización

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        set_time_limit(0); // Sin límite de tiempo
        ini_set('max_execution_time', 0);
    }

    /**
     * Sincronizar productos de MercadoLibre con WooCommerce
     */
    public function syncProducts($clientId, Request $request)
    {
        try {
            // Validar parámetros opcionales
            $syncMode = $request->query('mode', 'sync'); // 'sync' o 'create_only'
            $storeIds = $request->query('store_ids'); // IDs específicos de tiendas (opcional)
            
            Log::info('Iniciando sincronización MercadoLibre -> WooCommerce', [
                'client_id' => $clientId,
                'sync_mode' => $syncMode,
                'store_ids' => $storeIds,
                'user_id' => optional(Auth::user())->id
            ]);

            // Obtener productos de MercadoLibre
            $mlProducts = $this->getMercadoLibreProducts($clientId);
            
            if (empty($mlProducts)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron productos en MercadoLibre para sincronizar.',
                ], 404);
            }

            // Obtener tiendas WooCommerce
            $stores = $this->getWooCommerceStores($storeIds);
            
            if ($stores->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No hay tiendas WooCommerce activas para sincronizar.',
                ], 404);
            }

            $syncResults = [
                'client_id' => $clientId,
                'sync_mode' => $syncMode,
                'total_ml_products' => count($mlProducts),
                'total_stores' => $stores->count(),
                'stores_processed' => 0,
                'products_updated' => 0,
                'products_created' => 0,
                'products_skipped' => 0,
                'errors' => [],
                'store_results' => []
            ];

            foreach ($stores as $store) {
                try {
                    Log::info('Sincronizando con tienda WooCommerce', [
                        'store_id' => $store->id,
                        'store_name' => $store->name
                    ]);

                    $woocommerce = $this->connectToWooCommerce($store->id);
                    $storeResult = [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'products_updated' => 0,
                        'products_created' => 0,
                        'products_skipped' => 0,
                        'errors' => []
                    ];

                    // Procesar productos en lotes para evitar timeouts
                    $productBatches = array_chunk($mlProducts, $this->maxProductsPerBatch);
                    $batchCount = 0;
                    
                    foreach ($productBatches as $productBatch) {
                        $batchCount++;
                        Log::info('Procesando lote de productos', [
                            'store_id' => $store->id,
                            'batch' => $batchCount,
                            'total_batches' => count($productBatches),
                            'products_in_batch' => count($productBatch)
                        ]);
                        
                        foreach ($productBatch as $mlProduct) {
                            try {
                                $sku = $mlProduct['model'] ?? '';
                                
                                if (empty($sku)) {
                                    $storeResult['products_skipped']++;
                                    Log::warning('Producto sin SKU, saltando', [
                                        'ml_product_id' => $mlProduct['id'],
                                        'title' => $mlProduct['title']
                                    ]);
                                    continue;
                                }

                                // Buscar producto existente por SKU
                                $existingProducts = $woocommerce->get('products', [
                                    'sku' => $sku,
                                    'per_page' => 1
                                ]);

                                if (!empty($existingProducts)) {
                                    // Actualizar producto existente
                                    if ($syncMode === 'sync' || $syncMode === 'update') {
                                        $wooProduct = $existingProducts[0];
                                        $updateData = [
                                            'regular_price' => (string)$mlProduct['price'],
                                            'stock_quantity' => $mlProduct['available_quantity'],
                                            'manage_stock' => true,
                                            'stock_status' => $mlProduct['available_quantity'] > 0 ? 'instock' : 'outofstock'
                                        ];

                                        $woocommerce->put("products/{$wooProduct->id}", $updateData);
                                        $storeResult['products_updated']++;
                                        
                                        Log::info('Producto actualizado en WooCommerce', [
                                            'store_id' => $store->id,
                                            'sku' => $sku,
                                            'woo_product_id' => $wooProduct->id,
                                            'price' => $mlProduct['price'],
                                            'quantity' => $mlProduct['available_quantity']
                                        ]);
                                    } else {
                                        $storeResult['products_skipped']++;
                                    }
                                } else {
                                    // Crear nuevo producto
                                    if ($syncMode === 'sync' || $syncMode === 'create') {
                                        $newProductData = [
                                            'name' => $mlProduct['title'],
                                            'type' => 'simple',
                                            'regular_price' => (string)$mlProduct['price'],
                                            'sku' => $sku,
                                            'stock_quantity' => $mlProduct['available_quantity'],
                                            'manage_stock' => true,
                                            'stock_status' => $mlProduct['available_quantity'] > 0 ? 'instock' : 'outofstock',
                                            'status' => 'publish',
                                            'description' => $mlProduct['description'] ?? '',
                                            'short_description' => $mlProduct['title'],
                                            'images' => !empty($mlProduct['thumbnail']) ? [
                                                ['src' => $mlProduct['thumbnail']]
                                            ] : []
                                        ];

                                        $newProduct = $woocommerce->post('products', $newProductData);
                                        $storeResult['products_created']++;
                                        
                                        Log::info('Producto creado en WooCommerce', [
                                            'store_id' => $store->id,
                                            'sku' => $sku,
                                            'woo_product_id' => $newProduct->id,
                                            'price' => $mlProduct['price'],
                                            'quantity' => $mlProduct['available_quantity']
                                        ]);
                                    } else {
                                        $storeResult['products_skipped']++;
                                    }
                                }

                            } catch (\Exception $e) {
                                $error = [
                                    'sku' => $mlProduct['model'] ?? 'N/A',
                                    'ml_product_id' => $mlProduct['id'],
                                    'error' => $e->getMessage()
                                ];
                                $storeResult['errors'][] = $error;
                                $syncResults['errors'][] = $error;
                                
                                Log::error('Error sincronizando producto', $error);
                            }
                        }
                        
                        // Pausa entre lotes para evitar sobrecargar la API
                        if ($batchCount < count($productBatches)) {
                            Log::info('Pausa entre lotes', [
                                'store_id' => $store->id,
                                'batch' => $batchCount,
                                'pausa_segundos' => 2
                            ]);
                            sleep(2); // 2 segundos entre lotes
                        }
                    }

                    $syncResults['store_results'][] = $storeResult;
                    $syncResults['stores_processed']++;
                    $syncResults['products_updated'] += $storeResult['products_updated'];
                    $syncResults['products_created'] += $storeResult['products_created'];
                    $syncResults['products_skipped'] += $storeResult['products_skipped'];

                } catch (\Exception $e) {
                    $error = [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'error' => $e->getMessage()
                    ];
                    $syncResults['errors'][] = $error;
                    
                    Log::error('Error procesando tienda WooCommerce', $error);
                }
            }

            Log::info('Sincronización completada', $syncResults);

            return response()->json([
                'status' => 'success',
                'message' => 'Sincronización con WooCommerce completada exitosamente',
                'data' => $syncResults
            ]);

        } catch (\Exception $e) {
            Log::error('Error general en sincronización', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en sincronización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener productos de MercadoLibre
     */
    private function getMercadoLibreProducts($clientId)
    {
        try {
            // Cachear credenciales por 10 minutos
            $cacheKeyCred = 'ml_credentials_' . $clientId;
            $credentials = Cache::remember($cacheKeyCred, now()->addMinutes(10), function () use ($clientId) {
                return MercadoLibreCredential::where('client_id', $clientId)->first();
            });

            if (!$credentials) {
                throw new \Exception('No se encontraron credenciales válidas para el client_id proporcionado.');
            }

            // Refrescar token si es necesario
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);

                if ($refreshResponse->failed()) {
                    throw new \Exception('No se pudo refrescar el token de MercadoLibre');
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }

            // Obtener ID de usuario
            $userResponse = Http::withToken($credentials->access_token)
                ->get('https://api.mercadolibre.com/users/me');

            if ($userResponse->failed()) {
                throw new \Exception('No se pudo obtener el ID del usuario de MercadoLibre');
            }

            $userId = $userResponse->json()['id'];
            
            // Obtener productos usando search_type=scan
            $response = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                    'search_type' => 'scan',
                    'limit' => $this->batchSize,
                    'status' => 'active'
                ]);

            if ($response->failed()) {
                throw new \Exception('Error al obtener productos de MercadoLibre');
            }

            $data = $response->json();
            $productIds = $data['results'] ?? [];
            
            if (empty($productIds)) {
                return [];
            }

            // Obtener detalles de los productos
            $products = [];
            $client = new Client(['timeout' => 30]);
            
            // Procesar en lotes
            $itemBatches = array_chunk($productIds, 20);
            
            foreach ($itemBatches as $batch) {
                $batchIds = implode(',', $batch);
                $response = $client->get('https://api.mercadolibre.com/items', [
                    'query' => [
                        'ids' => $batchIds,
                        'attributes' => 'id,title,price,currency_id,available_quantity,sold_quantity,thumbnail,permalink,status,category_id'
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $credentials->access_token
                    ]
                ]);

                $itemData = json_decode($response->getBody(), true);
                
                foreach ($itemData as $itemResult) {
                    if (isset($itemResult['code']) && $itemResult['code'] == 200) {
                        $product = $itemResult['body'];
                        
                        // Obtener atributos para extraer el modelo (SKU)
                        $model = $this->extractModelFromProduct($product['id'], $credentials->access_token);
                        
                        $products[] = [
                            'id' => $product['id'],
                            'title' => $product['title'],
                            'price' => $product['price'],
                            'currency_id' => $product['currency_id'],
                            'available_quantity' => $product['available_quantity'],
                            'sold_quantity' => $product['sold_quantity'],
                            'thumbnail' => $product['thumbnail'],
                            'permalink' => $product['permalink'],
                            'status' => $product['status'],
                            'category_id' => $product['category_id'],
                            'model' => $model,
                            'description' => ''
                        ];
                    }
                }
                
                usleep(250000); // 250ms entre lotes
            }

            return $products;

        } catch (\Exception $e) {
            Log::error('Error obteniendo productos de MercadoLibre', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extraer modelo (SKU) de un producto de MercadoLibre
     */
    private function extractModelFromProduct($productId, $accessToken)
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get("https://api.mercadolibre.com/items/{$productId}");

            if ($response->failed()) {
                return '';
            }

            $product = $response->json();
            
            // Buscar en atributos
            if (isset($product['attributes']) && is_array($product['attributes'])) {
                foreach ($product['attributes'] as $attribute) {
                    $attributeId = $attribute['id'] ?? '';
                    $attributeValue = $attribute['value_name'] ?? '';

                    // Mapear atributos específicos para SKU
                    if (in_array($attributeId, ['MODEL', 'MODELO', 'SELLER_SKU', 'SKU', 'CODIGO', 'REFERENCE'])) {
                        return $attributeValue;
                    }
                }
            }

            return '';

        } catch (\Exception $e) {
            Log::warning("Error extrayendo modelo del producto {$productId}", [
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Iniciar sincronización asíncrona (recomendado para grandes volúmenes)
     */
    public function syncProductsAsync($clientId, Request $request)
    {
        try {
            $syncMode = $request->query('mode', 'sync');
            $storeIds = $request->query('store_ids');
            
            Log::info('Iniciando sincronización asíncrona', [
                'client_id' => $clientId,
                'sync_mode' => $syncMode,
                'store_ids' => $storeIds,
                'user_id' => optional(Auth::user())->id
            ]);

            // Obtener productos de MercadoLibre
            $mlProducts = $this->getMercadoLibreProducts($clientId);
            
            if (empty($mlProducts)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron productos en MercadoLibre para sincronizar.',
                ], 404);
            }

            // Obtener tiendas WooCommerce
            $stores = $this->getWooCommerceStores($storeIds);
            
            if ($stores->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No hay tiendas WooCommerce activas para sincronizar.',
                ], 404);
            }

            // Procesar en lotes más pequeños para el job
            $productBatches = array_chunk($mlProducts, $this->maxProductsPerBatch);
            
            foreach ($productBatches as $index => $productBatch) {
                foreach ($stores as $store) {
                    // Crear job para cada lote y tienda
                    SyncProductsJob::dispatch($clientId, $store->id, $productBatch, $syncMode)
                        ->delay(now()->addSeconds($index * 5)); // Espaciar jobs 5 segundos
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sincronización asíncrona iniciada. Los productos se procesarán en segundo plano.',
                'data' => [
                    'client_id' => $clientId,
                    'total_products' => count($mlProducts),
                    'total_stores' => $stores->count(),
                    'total_jobs' => count($productBatches) * $stores->count(),
                    'sync_mode' => $syncMode
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error iniciando sincronización asíncrona', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error iniciando sincronización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener tiendas WooCommerce
     */
    private function getWooCommerceStores($storeIds = null)
    {
        $query = WooStore::where('active', true);
        
        if ($storeIds) {
            $ids = is_array($storeIds) ? $storeIds : explode(',', $storeIds);
            $query->whereIn('id', $ids);
        }
        
        return $query->get();
    }

    /**
     * Conectar con WooCommerce
     */
    private function connectToWooCommerce($storeId)
    {
        $store = WooStore::findOrFail($storeId);
        
        return new WooCommerceClient(
            $store->store_url,
            $store->consumer_key,
            $store->consumer_secret,
            [
                'version' => 'wc/v3',
                'verify_ssl' => false
            ]
        );
    }
}
