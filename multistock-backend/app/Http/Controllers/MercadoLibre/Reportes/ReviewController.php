<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;

class ReviewController
{
    public function getReviewsByClientId($clientId, Request $request)
    {
        set_time_limit(300);

        try{
            $validated = $request->validate([
                'start_date' => 'sometimes|date_format:Y-m-d',
                'end_date' => 'sometimes|date_format:Y-m-d|after_or_equal:start_date',
                'limit_orders' => 'sometimes|integer|min:1|max:1000',
                'concurrent_requests' => 'sometimes|integer|min:1|max:20',
            ]);
            $startDate=null;
            $endDate=null;
            if(isset($validated['start_date'])){
                $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            }

            if(isset($validated['end_date'])){
                $endDate = Carbon::parse($validated['end_date'])->endOfDay();
            }

            $limitOrders = $validated['limit_orders'] ?? 1000;
            $concurrentRequests = $validated['concurrent_requests'] ?? 10;
        }catch(Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()

            ]);
        }
            // Validar parámetros opcionales


        // Obtener credenciales
        $cacheKey = 'ml_credentials_' . $clientId;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
            return MercadoLibreCredential::where('client_id', $clientId)->first();
        });

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Refrescar token si es necesario
        if ($credentials->isTokenExpired()) {
            $refreshResponse = $this->refreshToken($credentials);
            if ($refreshResponse) {
                return $refreshResponse;
            }
        }

        // Obtener ID del usuario
        $userId = $this->getUserId($credentials);
        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
            ], 500);
        }

        // Obtener órdenes pagadas dentro del rango de fechas
        $allOrders = $this->getAllOrdersByDateRange($credentials, $userId, $limitOrders, $startDate, $endDate);
        if (!is_array($allOrders)) {
            return $allOrders; // Retorna el error si hubo alguno
        }

        // Procesar reviews en paralelo
        $reviewsData = $this->processReviewsInParallel($credentials, $allOrders, $concurrentRequests);
        if($endDate && $startDate){
            return response()->json([
                'status' => 'success',
                'message' => 'Reviews obtenidas con éxito.',
                'date_range' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ],
                'total_products' => count($reviewsData),
                'total_orders' => count($allOrders),
                'data' => array_values($reviewsData),
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'total_products' => count($reviewsData),
            'total_orders' => count($allOrders),
            'data' => array_values($reviewsData),
        ]);

    }

    private function refreshToken($credentials)
    {
        $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $credentials->client_id,
            'client_secret' => $credentials->client_secret,
            'refresh_token' => $credentials->refresh_token,
        ]);

        if ($refreshResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo refrescar el token',
                'error' => $refreshResponse->json(),
            ], 401);
        }

        $data = $refreshResponse->json();
        $credentials->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return null;
    }

    private function getUserId($credentials)
    {
        $userResponse = Http::withToken($credentials->access_token)
            ->timeout(30)
            ->retry(3, 100)
            ->get('https://api.mercadolibre.com/users/me');

        return $userResponse->successful() ? $userResponse->json()['id'] : null;
    }

    private function getAllOrdersByDateRange($credentials, $userId, $limit, $startDate, $endDate)
    {
        $allOrders = [];
        $offset = 0;
        $pageSize = 50; // Tamaño máximo permitido por la API

        do {
            // Construir parámetros base
            $queryParams = [
                'seller' => $userId,
                'order.status' => 'paid',
                'limit' => $pageSize,
                'offset' => $offset,
            ];

            // Agregar fecha de inicio si está disponible
            if ($startDate !== null) {
                $queryParams['order.date_created.from'] = $startDate->format('Y-m-d\TH:i:s.Z\Z');
            }

            // Agregar fecha de fin si está disponible
            if ($endDate !== null) {
                $queryParams['order.date_created.to'] = $endDate->format('Y-m-d\TH:i:s.Z\Z');
            }

            $ordersResponse = Http::withToken($credentials->access_token)
                ->timeout(60)
                ->retry(3, 100)
                ->get("https://api.mercadolibre.com/orders/search", $queryParams);

            if ($ordersResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al obtener órdenes de MercadoLibre',
                    'error' => $ordersResponse->json(),
                ], $ordersResponse->status());
            }

            $data = $ordersResponse->json();
            $orders = $data['results'];
            $allOrders = array_merge($allOrders, $orders);
            $offset += $pageSize;

            // Si ya tenemos suficientes órdenes o no hay más, salir
            if (count($allOrders) >= $limit || empty($orders)) {
                break;
            }

        } while ($offset < ($data['paging']['total'] ?? PHP_INT_MAX));

        return $allOrders;
    }

    private function getUniqueProducts($allOrders)
    {
        $uniqueProducts = [];
        foreach ($allOrders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                if (!isset($uniqueProducts[$productId])) {
                    $uniqueProducts[$productId] = [
                        'name' => $item['item']['title'],
                        'price' => $item['unit_price'],
                    ];
                }
            }
        }
        return $uniqueProducts;
    }

    private function processReviewsInParallel($credentials, $allOrders, $concurrentLimit)
    {
        $client = new Client(['timeout' => 30]);
        $uniqueProducts = $this->getUniqueProducts($allOrders);
        $reviewsData = [];
        $productIds = array_keys($uniqueProducts);
        $productChunks = array_chunk($productIds, $concurrentLimit);

        foreach ($productChunks as $chunk) {
            $promises = [];

            foreach ($chunk as $productId) {
                $promises[$productId] = $client->getAsync(
                    "https://api.mercadolibre.com/reviews/item/{$productId}", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $credentials->access_token
                        ]
                    ]
                );
            }

            $responses = Promise\Utils::settle($promises)->wait();

            foreach ($responses as $productId => $response) {
                $productInfo = $uniqueProducts[$productId];

                if ($response['state'] === 'fulfilled') {
                    $reviews = json_decode($response['value']->getBody(), true);

                    $filteredReviews = array_filter(array_map(function ($review) {
                        return [
                            'rating' => $review['rating'] ?? null,
                            'comment' => $review['content'] ?? null,
                            'full_review' => $review['content'] ? $review : null,
                        ];
                    }, $reviews['reviews'] ?? []), function ($review) {
                        return !empty($review['comment']);
                    });

                    // Solo agregar si hay reviews
                    if (!empty($filteredReviews)) {
                        $reviewsData[$productId] = [
                            'product' => [
                                'id' => $productId,
                                'name' => $productInfo['name'],
                                'price' => $productInfo['price'],
                            ],
                            'reviews_count' => count($filteredReviews),
                            'reviews' => $filteredReviews,
                        ];
                    }
                } else {
                    Log::error("Error obteniendo reviews para producto $productId", [
                        'error' => $response['reason']->getMessage()
                    ]);
                }
            }

            if (count($productChunks) > 1) {
                usleep(200000); // Pequeña pausa entre chunks
            }
        }

        return $reviewsData;
    }
}
