<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;


class getChinaCarrier extends Controller
{
    public function chinaProductsAllCompanies(Request $request)
    {
        set_time_limit(600);
        Log::info('Inicio de consulta de productos internacionales China Carrier', ['request' => $request->all()]);

        // Validar parámetro
        if (!$request->has('company_id')) {
            Log::error('Falta parámetro company_id');
            return response()->json([
                'status' => 'error',
                'message' => 'Debe enviar el parámetro company_id.'
            ], 400);
        }

        $companyId = $request->input('company_id');
        $cacheKey = 'china_carrier_products_' . $companyId;
        $cachedResult = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cachedResult) {
            Log::info('Respuesta obtenida desde caché', ['company_id' => $companyId]);
            return response()->json($cachedResult);
        }

        $company = \App\Models\Company::find($companyId);
        if (!$company || !$company->client_id) {
            Log::error('No se encontró la compañía o no tiene client_id', ['company_id' => $companyId]);
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró la compañía o no tiene client_id asociado.'
            ], 404);
        }
        $clientId = $company->client_id;

        // Cachear credenciales por 10 minutos
        $clientId = $company->client_id;
        $cacheKey = 'ml_credentials_' . $clientId;
        $credentials = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
            \Illuminate\Support\Facades\Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
            return \App\Models\MercadoLibreCredential::where('client_id', $clientId)->first();
        });

        if (!$credentials) {
            Log::error('No se encontraron credenciales válidas', ['client_id' => $clientId]);
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Refresca token si está expirado
        if ($credentials->isTokenExpired()) {
            Log::info('Token expirado, refrescando...', ['client_id' => $credentials->client_id]);
            $refreshResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials->client_id,
                'client_secret' => $credentials->client_secret,
                'refresh_token' => $credentials->refresh_token,
            ]);
            if ($refreshResponse->failed()) {
                Log::error('No se pudo refrescar el token', ['client_id' => $credentials->client_id, 'response' => $refreshResponse->body()]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo refrescar el token',
                ], 401);
            }
            $data = $refreshResponse->json();
            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }

        // Obtener ID de usuario
        $userResponse = \Illuminate\Support\Facades\Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');
        if ($userResponse->failed()) {
            Log::error('No se pudo obtener el userId de MercadoLibre', ['response' => $userResponse->body()]);
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el userId de MercadoLibre.'
            ], 500);
        }
        $userId = $userResponse->json()['id'];

        $batchSize = 50;
        $internacionales = [];
        $seenIds = [];
        $hasMore = true;
        $scrollId = null;
        $totalItems = 0;
        $errorCount = 0;
        $successCount = 0;

        // Cliente HTTP para requests en paralelo
        $client = new \GuzzleHttp\Client([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials->access_token
            ]
        ]);

        // Primer request con search_type=scan
        $initialResponse = \Illuminate\Support\Facades\Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                'search_type' => 'scan',
                'limit' => $batchSize,
                'status' => 'active'
            ]);

        if ($initialResponse->failed()) {
            Log::error('Error al iniciar la búsqueda de productos', ['response' => $initialResponse->json()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al iniciar la búsqueda de productos',
                'details' => $initialResponse->json()
            ], 500);
        }

        $initialData = $initialResponse->json();
        $totalItems = $initialData['paging']['total'] ?? 0;
        $scrollId = $initialData['scroll_id'] ?? null;

        Log::info('Iniciando obtención de productos con scan', [
            'total_productos' => $totalItems,
            'scroll_id' => $scrollId,
            'response' => $initialData
        ]);

        // Obtener productos usando scroll_id
        while ($hasMore && $scrollId) {
            try {
                Log::info('Consultando productos con scroll', [
                    'scroll_id' => $scrollId,
                    'procesados' => count($internacionales)
                ]);

                $response = \Illuminate\Support\Facades\Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                        'search_type' => 'scan',
                        'scroll_id' => $scrollId,
                        'limit' => $batchSize
                    ]);

                if ($response->failed()) {
                    Log::error('Error en consulta con scroll', [
                        'scroll_id' => $scrollId,
                        'response' => $response->json()
                    ]);
                    $errorCount++;
                    break;
                }

                $data = $response->json();
                Log::info('Respuesta de scroll', [
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
                            'attributes' => 'id,title,price,available_quantity,condition,status,pictures,attributes,permalink,shipping,seller_address'
                        ]
                    ]);
                }

                // Esperar todas las respuestas en paralelo
                $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

                foreach ($responses as $response) {
                    if ($response['state'] === 'fulfilled') {
                        $itemData = json_decode($response['value']->getBody(), true);
                        foreach ($itemData as $itemResult) {
                            if (isset($itemResult['code']) && $itemResult['code'] == 200) {
                                $item = $itemResult['body'];
                                $id = $item['id'];
                                if (isset($seenIds[$id])) continue;
                                $logistic = $item['shipping']['logistic_type'] ?? '';
                                if (in_array($logistic, ['remote', 'cross_docking', 'xd_drop_off'])) {
                                    $internacionales[] = [
                                        'id' => $item['id'],
                                        'title' => $item['title'],
                                        'price' => $item['price'],
                                        'date_created' => $item['date_created'] ?? null,
                                        'available_quantity' => $item['available_quantity'],
                                        'condition' => $item['condition'],
                                        'status' => $item['status'],
                                        'pictures' => $item['pictures'],
                                        'attributes' => $item['attributes'],
                                        'permalink' => $item['permalink'],
                                        'logistic_type' => $logistic,
                                        'country' => $item['seller_address']['country']['id'] ?? '',
                                    ];
                                    $successCount++;
                                }
                                $seenIds[$id] = true;
                            }
                        }
                    } else {
                        $errorCount += 20;
                    }
                    usleep(50000); // 50ms entre lotes de 20
                }
                Log::info('Progreso internacionales', [
                    'total_productos' => $totalItems,
                    'procesados' => count($internacionales),
                    'porcentaje' => $totalItems > 0 ? round((count($internacionales) / $totalItems) * 100, 2) . '%' : '0%',
                    'scroll_id' => $scrollId
                ]);
                usleep(250000); // 250ms entre lotes de 50
            } catch (\Exception $e) {
                Log::error('Error general en iteración', [
                    'error' => $e->getMessage(),
                    'scroll_id' => $scrollId
                ]);
            }
        }

        Log::info('Total de productos internacionales encontrados', ['cantidad' => count($internacionales)]);

        $result = [
            'status' => 'success',
            'message' => 'Productos internacionales obtenidos correctamente.',
            'total_activos' => $totalItems,
            'mensaje_total_activos' => 'Total de productos activos encontrados: ' . $totalItems,
            'cantidad_internacionales' => count($internacionales),
            'mensaje_total_internacionales' => 'Total de productos internacionales encontrados: ' . count($internacionales),
            'products' => $internacionales,
            'success_count' => $successCount,
            'error_count' => $errorCount,
        ];
        // Guardar en caché por 10 minutos
        \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addMinutes(10));

        return response()->json($result);
    }
}