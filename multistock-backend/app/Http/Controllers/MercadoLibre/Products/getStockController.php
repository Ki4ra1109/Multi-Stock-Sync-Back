<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\error;

class getStockController
{
    public function getStock($clientId)
    {
        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $clientId;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
            return \App\Models\MercadoLibreCredential::where('client_id', $clientId)->first();
        });

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        try {
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);
                // Si la solicitud falla, devolver un mensaje de error
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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token: ' . $e->getMessage(),
            ], 500);
        }
        // Comprobar si el token ha expirado y refrescarlo si es necesario

//
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }
        $userId = $userResponse->json()['id'];
        $limit = 100;
        $offset = 0;
        $totalItems = 0;
        $productsStock = [];

        // Construir la URL base
        $baseUrl = "https://api.mercadolibre.com/users/{$userId}/items/search";
        try {

            $maxProductos = 1000; // Ajustar según necesidades (1000 es el maximo)
            $productosProcessed = 0; //contador de productos para terminar la ejecucion el caso de alcanzar $maxProductos
            //se setea los headres y el tiempo de espera de la conexion asyncrona
            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $credentials->access_token
                ]
            ]);
            do {
                // se arma la url para obtener lotes de IDs de productos para consultar a travez de ids
                $searchUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') .
                    http_build_query(['limit' => $limit, 'offset' => $offset]);
                error_log("URL: {$searchUrl}");
                $response = Http::timeout(30)->withToken($credentials->access_token)->get($searchUrl);
                if ($response->failed()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Error al conectar con la API de MercadoLibre.',
                        'error' => $response->json(),
                        'request_url' => $searchUrl,
                    ], $response->status());
                }
                $json = $response->json();
                $items = $json['results'] ?? [];
                $total = $json['paging']['total'] ?? 0;

                if (empty($items)) {
                    break; // No hay más items para procesar
                }
                //se separan los 100 items de la peticion en grupos de 20 que es el maximo
                // de items que se pueden pedir a la vez
                $itemBatches = array_chunk($items, 20);
                $totalItems += count($items);
                $productosProcessed += count($items);

                // Solucion azyncrona mmultiples peticiones paralelas
                foreach ($itemBatches as $batch) {
                    // Filtrar IDs ya procesados

                    if (empty($batch)) {
                        continue;
                    }

                    // Crear promesas para peticiones paralelas
                    $promises = [];
                    foreach (array_chunk($batch, 20) as $subBatch) {
                        $batchIds = implode(',', $subBatch);
                        $promises[] = $client->getAsync('https://api.mercadolibre.com/items', [
                            'query' => [
                                'ids' => $batchIds,
                                'attributes' => 'id,title,available_quantity,date_created,last_updated,attributes,seller_custom_field,seller_sku'
                            ]
                        ]);
                    }

                    // Ejecutar todas las promesas en paralelo
                    try {
                        $responses = Promise\Utils::unwrap($promises);
                        // Procesar cada respuesta de las promesas
                        foreach ($responses as $response) {
                            if ($response->getStatusCode() == 200) {
                                $batchResults = json_decode($response->getBody()->getContents(), true);

                                // Validar que batchResults sea un array antes de procesarlo
                                if (!is_array($batchResults)) {
                                    error_log("Error: La respuesta no es un array válido: " . $response->getBody());
                                    continue;
                                }

                                // Procesar los resultados
                                foreach ($batchResults as $itemResult) {
                                    if (
                                        isset($itemResult['code']) &&
                                        $itemResult['code'] == 200
                                    ) {
                                    $sku = null;
                                    $skuSource = null;

                                    // 1. Primero intenta con seller_custom_field
                                    if (!empty($itemResult['body']['seller_custom_field'])) {
                                        $sku = $itemResult['body']['seller_custom_field'];
                                        $skuSource = 'seller_custom_field';

                                    // 2. Luego intenta con seller_sku
                                    } elseif (!empty($itemResult['body']['seller_sku'])) {
                                        $sku = $itemResult['body']['seller_sku'];
                                        $skuSource = 'seller_sku';

                                    // 3. Luego busca en attributes (como fallback)
                                    } elseif (!empty($itemResult['body']['attributes'])) {
                                        foreach ($itemResult['body']['attributes'] as $attribute) {
                                            $id = strtolower($attribute['id'] ?? '');
                                            $name = strtolower($attribute['name'] ?? '');

                                            if (
                                                in_array($id, ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) ||
                                                in_array($name, ['sku', 'código', 'referencia', 'codigo', 'código de producto'])
                                            ) {
                                                $sku = $attribute['value_name'] ?? null;
                                                $skuSource = 'attributes';
                                                break;
                                            }
                                        }
                                    }

                                    // 4. Si aún no se encuentra, usa "modelo" como último recurso
                                    if (empty($sku) && !empty($itemResult['body']['attributes'])) {
                                        foreach ($itemResult['body']['attributes'] as $attribute) {
                                            if (
                                                strtolower($attribute['id'] ?? '') === 'model' ||
                                                strtolower($attribute['name'] ?? '') === 'modelo'
                                            ) {
                                                $sku = 'MOD-' . ($attribute['value_name'] ?? 'Desconocido');
                                                $skuSource = 'model_fallback';
                                                break;
                                            }
                                        }
                                    }

                                    // 5. Si no se encontró nada, marcar como no disponible
                                    if (empty($sku)) {
                                        $sku = 'Sin SKU';
                                        $skuSource = 'not_found';
                                    }
                                    $productsStock[] = [
                                        'id' => $itemResult['body']['id'],
                                        'title' => $itemResult['body']['title'],
                                        'available_quantity' => $itemResult['body']['available_quantity'],
                                        'stock_reload_date' => $itemResult['body']['date_created'],
                                        'purchase_sale_date' => $itemResult['body']['last_updated'],
                                        'sku' => $sku,
                                        'sku_source' => $skuSource,
                                        'details' => $itemResult['body']['attributes'],
                                    ];

                                    } else {
                                        error_log("Error al obtener producto: " . $itemResult['code'] . " - " . $itemResult['message']);
                                    }
                                }
                            } else {
                                error_log("Respuesta de API con estado no exitoso: " . $response->getStatusCode());
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Error en peticiones asincrónicas: " . $e->getMessage());
                        error_log("Traza del error: " . $e->getTraceAsString());
                    }
                }


                $offset += $limit;

                // terminar si se procesaron todos los productos
                if ($productosProcessed >= $maxProductos) {
                    break;
                }
            } while ($offset < $total);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar datos: ' . $e->getMessage(),
            ], 500);
        }


        return response()->json([
            'status' => 'success',
            'message' => 'Stock de productos obtenidos con éxito.',
            'data' => $productsStock,
            'total' => count($productsStock),
        ]);
    }
}