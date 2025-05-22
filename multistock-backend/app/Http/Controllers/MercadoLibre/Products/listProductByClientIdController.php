<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
class listProductByClientIdController
{
    /**
     * Get products from MercadoLibre API using client_id.
     */
    public function listProductsByClientId($clientId)
    {
        // Diccionario de estados traducidos
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
        if(false/*Cache::has($cacheKey)*/) {
            $cachedData = Cache::get($cacheKey);
            return response()->json([
                'status' => 'success',
                'message' => 'Datos obtenidos de cache',
                'data' => $cachedData,
                'from_cache' => true
            ]);
        }
        // Obtener credenciales por client_id

        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

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
        $products = [];
        $processedIds = [];

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
                error_log("Total: {$total}");
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
                    $uniqueBatch = array_diff($batch, $processedIds);
                    $processedIds = array_merge($processedIds, $uniqueBatch);
                    error_log("Batch size: " . count($uniqueBatch));
                    if (empty($uniqueBatch)) continue;

                    // Crear promesas para peticiones paralelas
                    $promises = [];
                    foreach (array_chunk($uniqueBatch, 20) as $subBatch) {
                        $batchIds = implode(',', $subBatch);
                        $promises[] = $client->getAsync('https://api.mercadolibre.com/items', [
                            'query' => [
                                'ids' => $batchIds,
                                'attributes' => 'id,title,price,currency_id,available_quantity,sold_quantity,thumbnail,permalink,status,category_id'
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
                                        $categoryName = $itemResult['body']['category_id']??'Desconocida';

                                        // Traducir estado si existe en el diccionario
                                        $status = $itemResult['body']['status'] ?? 'unknown';
                                        $translatedStatus = $statusDictionary[$itemResult['body']['status']] ?? $itemResult['body']['status'];
                                        $products[] = [
                                            'id' => $itemResult['body']['id'],
                                            'title' => $itemResult['body']['title'],
                                            'price' => $itemResult['body']['price'],
                                            'currency_id' => $itemResult['body']['currency_id'],
                                            'available_quantity' => $itemResult['body']['available_quantity'],
                                            'sold_quantity' => $itemResult['body']['sold_quantity'],
                                            'thumbnail' => $itemResult['body']['thumbnail'],
                                            'permalink' => $itemResult['body']['permalink'],
                                            'status' => $status,
                                            'status_translated' => $translatedStatus,
                                            'category_id' => $itemResult['body']['category_id'],
                                            'category_name' => $categoryName,
                                        ];
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

            $responseData = [
                'total_items_processed' => $totalItems,
                'products_count' => count($products),
                'productos' => $products

            ];


            Cache::put($cacheKey, $responseData, now()->addMinutes(10));

            return response()->json([
                'status' => 'success',
                'message' => 'Productos obtenidos correctamente',
                'data' => $responseData,
                'from_cache' => false
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar datos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
