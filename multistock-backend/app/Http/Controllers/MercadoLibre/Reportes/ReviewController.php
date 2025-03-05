<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class reviewController
{
    /**
     * Get reviews from MercadoLibre API using client_id.
     */
    public function getReviewsByClientId($clientId, $productId)
    {
        
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        
        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $userId = $response->json()['id'];

        
        $productResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/items/{$productId}");

        if ($productResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener la información del producto.',
                'error' => $productResponse->json(),
            ], 500);
        }

        $productData = $productResponse->json();
        $sellerId = $productData['seller_id'] ?? null;
        $productName = $productData['title'] ?? 'N/A';

        
        if (!$sellerId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró el seller_id del producto.',
            ], 400);
        }

        
        $reviewResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/reviews/item/{$productId}?access_token={$credentials->access_token}");

        if ($reviewResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudieron obtener las reviews.',
                'error' => $reviewResponse->json(),
            ], 500);
        }

        $reviews = $reviewResponse->json();

        
        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'data' => [
                'product' => [
                    'id' => $productId,
                    'name' => $productName,
                ],
                'reviews' => $reviews,
            ],
        ]);
    }

    /**
     * Get reviews from MercadoLibre API using client_id for multiple products.
     */
    public function getBatchReviewsByClientId(Request $request, $clientId)
    {
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $userId = $response->json()['id'];

        $year = $request->query('year', date('Y'));
        $month = $request->query('month');

        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);

        if ($month) {
            $dateFrom = "{$year}-{$month}-01T00:00:00.000-00:00";
            $dateTo = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom));
        } else {
            $dateFrom = "{$year}-01-01T00:00:00.000-00:00";
            $dateTo = "{$year}-12-31T23:59:59.999-00:00";
        }

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid&order.date_created.from={$dateFrom}&order.date_created.to={$dateTo}");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $reviewsData = [];
        $totalReviews = 0;

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $productName = $item['item']['title'];

                
                $reviewResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/reviews/item/{$productId}?access_token={$credentials->access_token}&limit={$perPage}&offset=" . (($page - 1) * $perPage));

                if ($reviewResponse->failed()) {
                    continue;
                }

                $reviews = $reviewResponse->json();
                if (!empty($reviews['reviews'])) {
                    $reviewsData[$productId] = [
                        'product' => [
                            'id' => $productId,
                            'name' => $productName,
                        ],
                        'reviews' => $reviews['reviews'],
                    ];
                    $totalReviews += count($reviews['reviews']);
                }
            }
        }

        // Return reviews data with product name and ID
        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'data' => [
                'paging' => [
                    'total' => $totalReviews,
                    'limit' => $perPage,
                    'offset' => ($page - 1) * $perPage,
                ],
                'reviews' => $reviewsData,
            ],
        ]);
    }
}
