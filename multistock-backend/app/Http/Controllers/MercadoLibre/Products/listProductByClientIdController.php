<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
class listProductByClientIdController
{
    private $batchSize = 50;

    public function listProductsByClientId($clientId)
    {
        ini_set('max_execution_time', 600); // 10 minutos

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
        
        try {
            // Obtener credenciales
            $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
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
                                        'category_name' => $itemResult['body']['category_id']
                                    ];
                                    $seenIds[$id] = true;
                                    $successCount++;
                                }
                            }
                        } else {
                            $errorCount += 20; // O cuenta los errores según el batch
                        }
                    }
                    
                    Log::info("Progreso", [
                        'total_productos' => $totalItems,
                        'procesados' => count($products),
                        'porcentaje' => round((count($products) / $totalItems) * 100, 2) . '%',
                        'scroll_id' => $scrollId
                    ]);

                    usleep(500000); // 500ms entre lotes de 50
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

            Log::info("Proceso completado", [
                'total_productos_inicial' => $totalItems,
                'total_productos_final' => count($products),
                'total_con_errores' => count($products) + $errorCount,
                'exitos' => $successCount,
                'errores' => $errorCount,
                'diferencia' => (count($products) + $errorCount) - $totalItems
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Productos obtenidos correctamente',
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
}
