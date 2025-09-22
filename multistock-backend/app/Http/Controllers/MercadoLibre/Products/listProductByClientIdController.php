<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\Utils;
class listProductByClientIdController
{
    private $batchSize = 50;

    public function listProductsByClientId($clientId)
    {
        ini_set('max_execution_time', 0); // Sin límite de tiempo


        $statusDictionary = [
            'active' => 'Activo',
            'paused' => 'Pausado',
            'closed' => 'Cerrado',
            'under_review' => 'En revisión',
            'inactive' => 'Inactivo',
            'payment_required' => 'Pago requerido',
            'not_yet_active' => 'Aún no activo',
            'deleted' => 'Eliminado',
        ];

        $cacheKey = "mercado_libre:products:{$clientId}";
        
        // Verificar si existe caché
        if (Cache::has($cacheKey)) {
            Log::info("Retornando datos desde caché para el cliente: {$clientId}");
            return response()->json([
                'status' => 'success',
                'message' => 'Productos obtenidos desde caché',
                'data' => Cache::get($cacheKey),
                'from_cache' => true
            ]);
        }

        try {
            // Cachear credenciales por 10 minutos
            $cacheKeyCred = 'ml_credentials_' . $clientId;
            $credentials = Cache::remember($cacheKeyCred, now()->addMinutes(10), function () use ($clientId) {
                Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
                return MercadoLibreCredential::where('client_id', $clientId)->first();
            });

            if (!$credentials) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron credenciales válidas.',
                ], 404);
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
                    return response()->json(['error' => 'No se pudo refrescar el token'], 401);
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
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo obtener el ID del usuario.',
                ], 500);
            }

            $userId = $userResponse->json()['id'];
            
            // Variables para el proceso
            $products = [];
            $seenIds = [];
            $hasMore = true;
            $successCount = 0;
            $errorCount = 0;
            $scrollId = null;

            // Cliente HTTP con timeout extendido
            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $credentials->access_token
                ]
            ]);

            // Obtener el total de productos usando search_type=scan
            $initialResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                    'search_type' => 'scan',
                    'limit' => $this->batchSize,
                    'status' => 'active'
                ]);

            if ($initialResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al iniciar la búsqueda de productos',
                    'details' => $initialResponse->json()
                ], 500);
            }

            $initialData = $initialResponse->json();
            $totalItems = $initialData['paging']['total'] ?? 0;
            $scrollId = $initialData['scroll_id'] ?? null;

            Log::info("Iniciando obtención de productos con scan", [
                'total_productos' => $totalItems,
                'scroll_id' => $scrollId,
                'response' => $initialData
            ]);

            // Obtener productos usando scroll_id
            while ($hasMore && $scrollId) {
                try {
                    Log::info("Consultando productos con scroll", [
                        'scroll_id' => $scrollId,
                        'procesados' => count($products)
                    ]);

                    $response = Http::withToken($credentials->access_token)
                        ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                            'search_type' => 'scan',
                            'scroll_id' => $scrollId,
                            'limit' => $this->batchSize
                        ]);

                    if ($response->failed()) {
                        Log::error("Error en consulta con scroll", [
                            'scroll_id' => $scrollId,
                            'response' => $response->json()
                        ]);
                        $errorCount++;
                        break; // Salimos del bucle si hay un error
                    }

                    $data = $response->json();
                    Log::info("Respuesta de scroll", [
                        'scroll_id' => $data['scroll_id'] ?? null,
                        'total' => $data['paging']['total'] ?? 0,
                        'items_count' => count($data['results'] ?? [])
                    ]);

                    $items = $data['results'] ?? [];
                    $scrollId = $data['scroll_id'] ?? null;

                    if (empty($items) || !$scrollId) {
                        $hasMore = false;
                        continue;
                    }

                    // Filtrar IDs ya vistos
                    $newIds = array_filter($items, function($id) use ($seenIds) {
                        return !isset($seenIds[$id]);
                    });

                    if (empty($newIds)) {
                        continue;
                    }

                    // Procesar en lotes de 20
                    $itemBatches = array_chunk($newIds, 20);
                    $promises = [];

                    foreach ($itemBatches as $batch) {
                        $batchIds = implode(',', $batch);
                        $promises[] = $client->getAsync('https://api.mercadolibre.com/items', [
                            'query' => [
                                'ids' => $batchIds,
                                'attributes' => 'id,title,price,currency_id,available_quantity,sold_quantity,thumbnail,permalink,status,category_id'
                            ]
                        ]);
                    }

                    // Esperar todas las respuestas en paralelo
                    $responses = Promise\Utils::settle($promises)->wait();

                    foreach ($responses as $response) {
                        if ($response['state'] === 'fulfilled') {
                            $itemData = json_decode($response['value']->getBody(), true);
                            foreach ($itemData as $itemResult) {
                                if (isset($itemResult['code']) && $itemResult['code'] == 200) {
                                    $id = $itemResult['body']['id'];
                                    if (isset($seenIds[$id])) continue;

                                    $status = $itemResult['body']['status'] ?? 'unknown';
                                    
                                    // Agregar producto básico primero
                                    $products[] = [
                                        'id' => $id,
                                        'title' => $itemResult['body']['title'],
                                        'price' => $itemResult['body']['price'],
                                        'currency_id' => $itemResult['body']['currency_id'],
                                        'available_quantity' => $itemResult['body']['available_quantity'],
                                        'sold_quantity' => $itemResult['body']['sold_quantity'],
                                        'thumbnail' => $itemResult['body']['thumbnail'],
                                        'permalink' => $itemResult['body']['permalink'],
                                        'status' => $status,
                                        'status_translated' => $statusDictionary[$status] ?? $status,
                                        'category_id' => $itemResult['body']['category_id'],
                                        'category_name' => $itemResult['body']['category_id'],
                                        'description' => '',
                                        'model' => '',
                                        'size' => '',
                                        'color' => '',
                                        'brand' => '',
                                        'attributes' => []
                                    ];
                                    $seenIds[$id] = true;
                                    $successCount++;
                                }
                            }
                        } else {
                            $errorCount += 20; // O cuenta los errores según el batch
                        }
                      
                        usleep(50000); // 50ms entre lotes de 20

                    }
                    
                    Log::info("Progreso", [
                        'total_productos' => $totalItems,
                        'procesados' => count($products),
                        'porcentaje' => round((count($products) / $totalItems) * 100, 2) . '%',
                        'scroll_id' => $scrollId
                    ]);

                    usleep(250000); // 250ms entre lotes de 50
                } catch (\Exception $e) {
                    Log::error("Error general en iteración", [
                        'error' => $e->getMessage(),
                        'scroll_id' => $scrollId
                    ]);
                }
            }

            $responseData = [
                'total_available' => $totalItems,
                'total_processed' => count($products),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'productos' => $products,
                'details' => [
                    'initial_total' => $totalItems,
                    'final_total' => count($products) + $errorCount,
                    'difference' => (count($products) + $errorCount) - $totalItems,
                    'completion_percentage' => round((count($products) / $totalItems) * 100, 2) . '%'
                ]
            ];

            Cache::put($cacheKey, $responseData, now()->addMinutes(10));

            Log::info("Proceso completado - obteniendo detalles adicionales", [
                'total_productos_inicial' => $totalItems,
                'total_productos_final' => count($products),
                'total_con_errores' => count($products) + $errorCount,
                'exitos' => $successCount,
                'errores' => $errorCount,
                'diferencia' => (count($products) + $errorCount) - $totalItems
            ]);

            // Versión optimizada: solo enriquecer los primeros 10 productos
            $productsToEnrich = array_slice($products, 0, 10);
            $enrichedProducts = $this->enrichProductsWithDetails($productsToEnrich, $credentials->access_token);
            
            // Combinar productos enriquecidos con el resto
            $remainingProducts = array_slice($products, 10);
            $allProducts = array_merge($enrichedProducts, $remainingProducts);

            Log::info("Detalles adicionales completados", [
                'productos_enriquecidos' => count($enrichedProducts),
                'productos_sin_enriquecer' => count($remainingProducts),
                'total_productos' => count($allProducts)
            ]);

            // Actualizar responseData con productos enriquecidos
            $responseData['productos'] = $allProducts;

            return response()->json([
                'status' => 'success',
                'message' => 'Productos obtenidos correctamente con detalles completos',
                'data' => $responseData,
                'from_cache' => false
            ]);

        } catch (\Exception $e) {
            Log::error("Error general", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar datos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enriquecer productos con detalles adicionales de forma eficiente
     */
    private function enrichProductsWithDetails($products, $accessToken)
    {
        $enrichedProducts = [];
        $batchSize = 10; // Reducir a lotes de 10 productos para mejor rendimiento
        $totalProducts = count($products);
        
        Log::info("Iniciando enriquecimiento de productos", [
            'total_productos' => $totalProducts,
            'batch_size' => $batchSize
        ]);

        for ($i = 0; $i < $totalProducts; $i += $batchSize) {
            $batch = array_slice($products, $i, $batchSize);
            $batchIds = array_column($batch, 'id');
            
            Log::info("Procesando lote de productos", [
                'lote' => ($i / $batchSize) + 1,
                'productos_en_lote' => count($batch),
                'ids' => $batchIds
            ]);

            // Procesar lote en paralelo
            $promises = [];
            $client = new Client(['timeout' => 15]);
            
            foreach ($batchIds as $productId) {
                $promises[$productId] = $client->getAsync("https://api.mercadolibre.com/items/{$productId}", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken
                    ]
                ]);
            }

            // Esperar todas las respuestas del lote
            $responses = Utils::settle($promises)->wait();

            foreach ($batch as $product) {
                $productId = $product['id'];
                
                if (isset($responses[$productId]) && $responses[$productId]['state'] === 'fulfilled') {
                    $productData = json_decode($responses[$productId]['value']->getBody(), true);
                    $details = $this->extractProductDetails($productData, $accessToken);
                    
                    // Actualizar producto con detalles
                    $product['description'] = $details['description'];
                    $product['model'] = $details['model'];
                    $product['size'] = $details['size'];
                    $product['color'] = $details['color'];
                    $product['brand'] = $details['brand'];
                    $product['attributes'] = $details['attributes'];
                } else {
                    Log::warning("No se pudieron obtener detalles del producto {$productId}");
                }
                
                $enrichedProducts[] = $product;
            }

            // Pausa entre lotes para no sobrecargar la API
            if ($i + $batchSize < $totalProducts) {
                usleep(1000000); // 1 segundo entre lotes para mejor estabilidad
            }
        }

        Log::info("Enriquecimiento completado", [
            'productos_originales' => $totalProducts,
            'productos_enriquecidos' => count($enrichedProducts)
        ]);

        return $enrichedProducts;
    }

    /**
     * Extraer detalles de un producto desde los datos de la API
     */
    private function extractProductDetails($productData, $accessToken)
    {
        // Usar solo el título como descripción (más rápido)
        $description = $productData['title'] ?? '';

        // Extraer atributos específicos
        $model = '';
        $size = '';
        $color = '';
        $brand = '';
        $attributes = [];

        if (isset($productData['attributes']) && is_array($productData['attributes'])) {
            foreach ($productData['attributes'] as $attribute) {
                $attributeId = $attribute['id'] ?? '';
                $attributeName = $attribute['name'] ?? '';
                $attributeValue = $attribute['value_name'] ?? '';

                $attributes[] = [
                    'id' => $attributeId,
                    'name' => $attributeName,
                    'value' => $attributeValue
                ];

                // Mapear atributos específicos
                switch ($attributeId) {
                    case 'MODEL':
                    case 'MODELO':
                        $model = $attributeValue;
                        break;
                    case 'SIZE':
                    case 'TALLA':
                    case 'TAMAÑO':
                        $size = $attributeValue;
                        break;
                    case 'COLOR':
                    case 'COLOUR':
                        $color = $attributeValue;
                        break;
                    case 'BRAND':
                    case 'MARCA':
                        $brand = $attributeValue;
                        break;
                }
            }
        }

        return [
            'description' => $description,
            'model' => $model,
            'size' => $size,
            'color' => $color,
            'brand' => $brand,
            'attributes' => $attributes
        ];
    }

    /**
     * Obtener detalles adicionales de un producto específico
     */
    private function getProductDetails($productId, $accessToken)
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get("https://api.mercadolibre.com/items/{$productId}");

            if ($response->failed()) {
                Log::warning("No se pudieron obtener detalles del producto {$productId}");
                return [
                    'description' => '',
                    'model' => '',
                    'size' => '',
                    'color' => '',
                    'brand' => '',
                    'attributes' => []
                ];
            }

            $product = $response->json();
            
            // Extraer descripción
            $description = '';
            if (isset($product['descriptions']) && !empty($product['descriptions'])) {
                $descriptionResponse = Http::withToken($accessToken)
                    ->timeout(5)
                    ->get("https://api.mercadolibre.com/items/{$productId}/descriptions");
                
                if ($descriptionResponse->successful()) {
                    $descriptions = $descriptionResponse->json();
                    if (!empty($descriptions) && isset($descriptions[0]['plain_text'])) {
                        $description = $descriptions[0]['plain_text'];
                    }
                }
            }

            // Extraer atributos específicos
            $model = '';
            $size = '';
            $color = '';
            $brand = '';
            $attributes = [];

            if (isset($product['attributes']) && is_array($product['attributes'])) {
                foreach ($product['attributes'] as $attribute) {
                    $attributeId = $attribute['id'] ?? '';
                    $attributeName = $attribute['name'] ?? '';
                    $attributeValue = $attribute['value_name'] ?? '';

                    $attributes[] = [
                        'id' => $attributeId,
                        'name' => $attributeName,
                        'value' => $attributeValue
                    ];

                    // Mapear atributos específicos
                    switch ($attributeId) {
                        case 'MODEL':
                        case 'MODELO':
                            $model = $attributeValue;
                            break;
                        case 'SIZE':
                        case 'TALLA':
                        case 'TAMAÑO':
                            $size = $attributeValue;
                            break;
                        case 'COLOR':
                        case 'COLOUR':
                            $color = $attributeValue;
                            break;
                        case 'BRAND':
                        case 'MARCA':
                            $brand = $attributeValue;
                            break;
                    }
                }
            }

            return [
                'description' => $description,
                'model' => $model,
                'size' => $size,
                'color' => $color,
                'brand' => $brand,
                'attributes' => $attributes
            ];

        } catch (\Exception $e) {
            Log::error("Error al obtener detalles del producto {$productId}", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'description' => '',
                'model' => '',
                'size' => '',
                'color' => '',
                'brand' => '',
                'attributes' => []
            ];
        }
    }
}
