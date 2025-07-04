<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Collection;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Cache;

class listProductsFromChinaController extends Controller
{
    private $maxExecutionTime = 1800;
    private $perPage = 200;
    private $totalProcessed = 0;
    private $chinaProducts = [];
    private $startTime;
    private $concurrentRequests = 20;
    private $client;
    private $processedIds = [];
    private $debugMode = true;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 30
        ]);
    }

    public function listProductsFromChina($clientId)
    {
        try {
            set_time_limit(900);
            ini_set('max_execution_time', '900');
            ini_set('memory_limit', '1G');
            
            $this->startTime = microtime(true);
            Log::info('Iniciando búsqueda de productos de China', ['client_id' => $clientId]);

            // Verificar cache
            $cacheKey = "mercado_libre:china_products:{$clientId}";
            if (Cache::has($cacheKey)) {
                Log::info("Retornando productos de China desde caché para el cliente: {$clientId}");
                return response()->json([
                    'status' => 'success',
                    'message' => 'Productos de China obtenidos desde caché',
                    'data' => Cache::get($cacheKey),
                    'from_cache' => true
                ]);
            }

            $credentials = $this->getAndValidateCredentials($clientId);
            if (!$credentials['success']) {
                return response()->json($credentials['response'], $credentials['status']);
            }

            $userId = $this->getUserId($credentials['data']);
            if (!$userId['success']) {
                return response()->json($userId['response'], $userId['status']);
            }

            Log::info('Iniciando procesamiento', [
                'concurrent_requests' => $this->concurrentRequests,
                'per_page' => $this->perPage
            ]);

            $result = $this->processAllProducts($credentials['data'], $userId['data']);

            $tiempoTotal = round(microtime(true) - $this->startTime, 2);
            $velocidadPromedio = $this->totalProcessed > 0 ? round($this->totalProcessed / $tiempoTotal, 2) : 0;

            $responseData = [
                'estadisticas' => [
                    'total_productos' => $result['total_productos'] ?? 0,
                    'total_procesados' => $this->totalProcessed,
                    'productos_china' => count($this->chinaProducts),
                    'tiempo_total' => $tiempoTotal . ' segundos',
                    'velocidad_promedio' => $velocidadPromedio . ' productos/segundo',
                    'tiempo_limite_alcanzado' => $this->isTimeExceeded(),
                    'productos_por_segundo' => $velocidadPromedio,
                    'eficiencia' => $this->totalProcessed > 0 ? round((count($this->chinaProducts) / $this->totalProcessed) * 100, 2) . '%' : '0%'
                ],
                'data' => $this->chinaProducts,
                'configuracion' => [
                    'max_execution_time' => $this->maxExecutionTime . ' segundos',
                    'per_page' => $this->perPage,
                    'concurrent_requests' => $this->concurrentRequests
                ]
            ];

            // Guardar en cache por 10 minutos
            Cache::put($cacheKey, $responseData, now()->addMinutes(10));

            return response()->json([
                'status' => 'success',
                'message' => 'Productos de China obtenidos exitosamente',
                'data' => $responseData,
                'from_cache' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error en el procesamiento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'total_procesados' => $this->totalProcessed
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error durante el procesamiento',
                'error' => $e->getMessage(),
                'productos_procesados' => $this->totalProcessed
            ], 500);
        }
    }

    private function processAllProducts($credentials, $userId)
    {
        $seenIds = [];
        $hasMore = true;
        $successCount = 0;
        $errorCount = 0;
        $scrollId = null;
        $batchCount = 0;
        $totalProductos = 0;

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
                'limit' => $this->perPage,
                'status' => 'active'
            ]);

        if ($initialResponse->failed()) {
            Log::error('Error al iniciar la búsqueda de productos', [
                'response' => $initialResponse->json()
            ]);
            return;
        }

        $initialData = $initialResponse->json();
        $totalItems = $initialData['paging']['total'] ?? 0;
        $totalProductos = $totalItems;
        $scrollId = $initialData['scroll_id'] ?? null;

        Log::info("Iniciando obtención de productos con scan", [
            'total_productos' => $totalItems,
            'scroll_id' => $scrollId,
            'response' => $initialData
        ]);

        // Obtener productos usando scroll_id
        while ($hasMore && $scrollId && !$this->isTimeExceeded()) {
            try {
                $batchCount++;
                
                Log::info("Procesando lote #{$batchCount}", [
                    'total_productos' => $totalProductos,
                    'scroll_id' => $scrollId,
                    'procesados' => $this->totalProcessed,
                    'productos_china_encontrados' => count($this->chinaProducts),
                    'progreso' => $totalItems > 0 ? round(($this->totalProcessed / $totalItems) * 100, 2) . '%' : '0%',
                    'tiempo_transcurrido' => round(microtime(true) - $this->startTime, 2) . ' segundos'
                ]);

                $response = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                        'search_type' => 'scan',
                        'scroll_id' => $scrollId,
                        'limit' => $this->perPage
                    ]);

                if ($response->failed()) {
                    Log::error("Error en consulta con scroll", [
                        'scroll_id' => $scrollId,
                        'response' => $response->json()
                    ]);
                    $errorCount++;
                    break;
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
                            'ids' => $batchIds
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

                                $productDetails = $itemResult['body'];
                                
                                if ($this->isChineseProduct($productDetails)) {
                                    Log::info('¡Producto de China encontrado!', [
                                        'product_id' => $id,
                                        'title' => $productDetails['title'] ?? 'N/A'
                                    ]);
                                    $this->chinaProducts[] = $this->formatProductData($productDetails);
                                }
                                
                                $seenIds[$id] = true;
                                $successCount++;
                                $this->totalProcessed++;
                            }
                        }
                    } else {
                        $errorCount += 20;
                    }
                    
                    usleep(50000); // 50ms entre lotes de 20
                }

                // Desactivar modo debug después de procesar 50 productos
                if ($this->debugMode && $this->totalProcessed > 50) {
                    $this->debugMode = false;
                    Log::info('Modo debug desactivado después de procesar 50 productos');
                }

                Log::info('Progreso de procesamiento', [
                    'total_productos' => $totalProductos,
                    'procesados' => $this->totalProcessed,
                    'total' => $totalItems,
                    'porcentaje' => $totalItems > 0 ? round(($this->totalProcessed / $totalItems) * 100, 2) . '%' : '0%',
                    'productos_china' => count($this->chinaProducts),
                    'tiempo_transcurrido' => round(microtime(true) - $this->startTime, 2) . ' segundos',
                    'tiempo_restante' => round($this->maxExecutionTime - (microtime(true) - $this->startTime), 2) . ' segundos'
                ]);

                usleep(250000); // 250ms entre lotes
                
            } catch (\Exception $e) {
                Log::error("Error general en iteración", [
                    'error' => $e->getMessage(),
                    'scroll_id' => $scrollId
                ]);
                $errorCount++;
            }
        }
        
        Log::info('Procesamiento completado', [
            'total_productos' => $totalProductos,
            'total_procesados' => $this->totalProcessed,
            'productos_china_encontrados' => count($this->chinaProducts),
            'tiempo_total' => round(microtime(true) - $this->startTime, 2) . ' segundos',
            'lotes_procesados' => $batchCount,
            'exitos' => $successCount,
            'errores' => $errorCount,
            'procesamiento_completo' => $this->totalProcessed >= $totalItems
        ]);

        return [
            'total_productos' => $totalProductos
        ];
    }





    private function isChineseProduct($product)
    {
        $searchTerms = [
            // Términos básicos
            'china', 'chinese', '中国', 'zhongguo', 'made in china', 'made in china',
            'fabricado en china', 'manufacturado en china', 'origen china',
            
            // Ciudades y regiones chinas
            'shenzhen', 'guangdong', 'shanghai', 'ningbo', 'yiwu', 'beijing', 'guangzhou',
            '深圳', '广东', '上海', '义乌', '北京', '广州', '宁波',
            
            // Plataformas y comercio
            'alibaba', 'aliexpress', 'taobao', 'tmall', 'jd.com', '1688.com',
            'importado', 'imported', 'imported from china', 'wholesale', 'bulk',
            'mayorista', 'al por mayor', 'distribuidor', 'proveedor',
            
            // Términos comerciales
            'dropshipping', 'dropship', 'reseller', 'revendedor', 'reventa',
            'genérico', 'generic', 'no brand', 'sin marca', 'unbranded',
            'white label', 'etiqueta blanca', 'oem', 'odm',
            
            // Categorías comunes de productos chinos
            'electronics', 'electrónicos', 'gadgets', 'accesorios', 'accessories',
            'phone', 'celular', 'smartphone', 'tablet', 'laptop', 'computadora',
            'cable', 'cables', 'usb', 'charger', 'cargador', 'power bank',
            'case', 'carcasa', 'cover', 'protector', 'screen protector',
            'headphones', 'auriculares', 'earphones', 'audífonos',
            'watch', 'reloj', 'smartwatch', 'fitness tracker',
            'toy', 'juguete', 'juego', 'game', 'hobby', 'pasatiempo',
            'clothing', 'ropa', 'vestimenta', 'fashion', 'moda',
            'shoes', 'zapatos', 'calzado', 'sneakers', 'tenis',
            'bag', 'bolsa', 'mochila', 'backpack', 'handbag',
            'home', 'casa', 'hogar', 'kitchen', 'cocina', 'bathroom', 'baño',
            'beauty', 'belleza', 'cosmetics', 'cosméticos', 'skincare',
            'health', 'salud', 'medical', 'médico', 'fitness', 'exercise',
            'automotive', 'automotriz', 'car', 'auto', 'vehicle', 'vehículo',
            
            // Términos de calidad y precio
            'cheap', 'barato', 'económico', 'affordable', 'budget', 'low cost',
            'quality', 'calidad', 'premium', 'high quality', 'alta calidad',
            'original', 'genuine', 'authentic', 'auténtico', 'original',
            'replica', 'réplica', 'copy', 'copia', 'fake', 'falso',
            
            // Términos de envío y logística
            'free shipping', 'envío gratis', 'free delivery', 'entrega gratis',
            'express', 'rápido', 'fast', 'urgent', 'urgente',
            'dhl', 'fedex', 'ups', 'ems', 'china post', 'correo chino',
            
            // Términos de negocio
            'business', 'negocio', 'commercial', 'comercial', 'trade', 'comercio',
            'supplier', 'proveedor', 'manufacturer', 'fabricante', 'factory', 'fábrica',
            'wholesale price', 'precio mayorista', 'bulk price', 'precio por volumen',
            'minimum order', 'pedido mínimo', 'moq', 'minimum quantity',
            
            // Términos específicos de MercadoLibre
            'nuevo', 'new', 'usado', 'used', 'reacondicionado', 'refurbished',
            'garantía', 'warranty', 'devolución', 'return', 'reembolso', 'refund'
        ];

        // Verificar en atributos
        if (isset($product['attributes'])) {
            foreach ($product['attributes'] as $attribute) {
                $attributeIds = [
                    'origin', 'manufacturer_country', 'country_of_origin', 'country',
                    'ITEM_CONDITION', 'MANUFACTURER', 'BRAND', 'SELLER_SKU', 'SKU',
                    'model', 'modelo', 'series', 'serie', 'type', 'tipo',
                    'material', 'materials', 'materiales', 'fabric', 'tela',
                    'color', 'colores', 'size', 'talla', 'dimension', 'dimensiones'
                ];
                
                if (in_array(strtolower($attribute['id']), $attributeIds)) {
                    $value = strtolower($attribute['value_name'] ?? '');
                    foreach ($searchTerms as $term) {
                        if (stripos($value, strtolower($term)) !== false) {
                            if ($this->debugMode) {
                                Log::info('Coincidencia en atributo', [
                                    'attribute_id' => $attribute['id'],
                                    'attribute_value' => $attribute['value_name'],
                                    'matched_term' => $term
                                ]);
                            }
                            return true;
                        }
                    }
                }
            }
        }

        // Verificar en el título
        $title = strtolower($product['title'] ?? '');
        foreach ($searchTerms as $term) {
            if (stripos($title, strtolower($term)) !== false) {
                if ($this->debugMode) {
                    Log::info('Coincidencia en título', [
                        'title' => $product['title'],
                        'matched_term' => $term
                    ]);
                }
                return true;
            }
        }

        // Verificar en la descripción
        if (isset($product['description'])) {
            $description = strtolower($product['description']);
            foreach ($searchTerms as $term) {
                if (stripos($description, strtolower($term)) !== false) {
                    if ($this->debugMode) {
                        Log::info('Coincidencia en descripción', [
                            'description_preview' => substr($product['description'], 0, 100) . '...',
                            'matched_term' => $term
                        ]);
                    }
                    return true;
                }
            }
        }

        // Verificar en el seller_sku
        if (isset($product['seller_sku'])) {
            $sku = strtolower($product['seller_sku']);
            foreach ($searchTerms as $term) {
                if (stripos($sku, strtolower($term)) !== false) {
                    if ($this->debugMode) {
                        Log::info('Coincidencia en SKU', [
                            'seller_sku' => $product['seller_sku'],
                            'matched_term' => $term
                        ]);
                    }
                    return true;
                }
            }
        }

        return false;
    }

    private function isTimeExceeded()
    {
        return (microtime(true) - $this->startTime) > $this->maxExecutionTime;
    }

    private function getAndValidateCredentials($clientId)
    {
        try {
            $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

            if (!$credentials) {
                Log::warning('Credenciales no encontradas', ['client_id' => $clientId]);
                return [
                    'success' => false,
                    'response' => [
                        'status' => 'error',
                        'message' => 'No se encontraron credenciales válidas.'
                    ],
                    'status' => 404
                ];
            }

            if ($credentials->isTokenExpired()) {
                Log::info('Refrescando token expirado', ['client_id' => $clientId]);
                
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);

                if ($refreshResponse->failed()) {
                    Log::error('Error al refrescar token', [
                        'client_id' => $clientId,
                        'error' => $refreshResponse->json()
                    ]);
                    return [
                        'success' => false,
                        'response' => ['error' => 'No se pudo refrescar el token'],
                        'status' => 401
                    ];
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }

            return ['success' => true, 'data' => $credentials];
        } catch (\Exception $e) {
            Log::error('Error en validación de credenciales', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getUserId($credentials)
    {
        try {
            $response = Http::withToken($credentials->access_token)
                ->get('https://api.mercadolibre.com/users/me');

            if ($response->failed()) {
                Log::error('Error al obtener ID de usuario', [
                    'error' => $response->json()
                ]);
                return [
                    'success' => false,
                    'response' => [
                        'status' => 'error',
                        'message' => 'No se pudo obtener el ID del usuario.'
                    ],
                    'status' => 500
                ];
            }

            return ['success' => true, 'data' => $response->json()['id']];
        } catch (\Exception $e) {
            Log::error('Error al obtener ID de usuario', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }



    private function formatProductData($product)
    {
        try {
            return [
                'title' => $product['title'],
                'price' => $product['price'],
                'currency_id' => $product['currency_id'],
                'available_quantity' => $product['available_quantity'],
                'sold_quantity' => $product['sold_quantity'],
                'status' => $product['status'],
                'condition' => $product['condition'],
                'permalink' => $product['permalink'],
                'seller_sku' => $product['seller_sku'] ?? 'N/A',
                'shipping' => [
                    'free_shipping' => $product['shipping']['free_shipping'] ?? false,
                    'mode' => $product['shipping']['mode'] ?? 'N/A',
                    'tags' => $product['shipping']['tags'] ?? []
                ],
                'attributes' => array_map(function($attr) {
                    return [
                        'id' => $attr['id'],
                        'name' => $attr['name'],
                        'value_name' => $attr['value_name'] ?? null
                    ];
                }, $product['attributes'] ?? []),
                'pictures' => array_map(function($pic) {
                    return $pic['url'];
                }, $product['pictures'] ?? []),
                'last_updated' => now()->toDateTimeString(),
                'id' => $product['id']
            ];
        } catch (\Exception $e) {
            Log::error('Error al formatear datos del producto', [
                'product_id' => $product['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
