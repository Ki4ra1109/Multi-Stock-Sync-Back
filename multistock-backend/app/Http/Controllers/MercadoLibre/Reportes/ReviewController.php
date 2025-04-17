<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class reviewController
{
    public function getReviewsByClientId($clientId)
    {
        set_time_limit(300);

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

        // Obtener ID del usuario
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                'error' => $userResponse->json(),
            ], 500);
        }

        $userId = $userResponse->json()['id'];

        // Paginación para traer todas las órdenes
        $limit = 50;
        $offset = 0;
        $allOrders = [];

        do {
            $ordersResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/orders/search", [
                    'seller' => $userId,
                    'order.status' => 'paid',
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if ($ordersResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al conectar con la API de MercadoLibre.',
                    'error' => $ordersResponse->json(),
                ], $ordersResponse->status());
            }

            $data = $ordersResponse->json();
            $orders = $data['results'];
            $total = $data['paging']['total'] ?? count($orders);

            $allOrders = array_merge($allOrders, $orders);
            $offset += $limit;

        } while ($offset < $total);

        $reviewsData = [];
        $productCounter = 0;

        foreach ($allOrders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $productName = $item['item']['title'];
                $productPrice = $item['unit_price'];

                // Solo consultar una vez por producto
                if (isset($reviewsData[$productId])) {
                    continue;
                }

                $reviewResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/reviews/item/{$productId}");

                if ($reviewResponse->failed()) {
                    continue;
                }

                $reviews = $reviewResponse->json();
                $filteredReviews = array_filter(array_map(function ($review) {
                    return [
                        'rating' => $review['rating'] ?? null,
                        'comment' => $review['content'] ?? null,
                        'full_review' => $review['content'] ? $review : null,
                    ];
                }, $reviews['reviews'] ?? []), function ($review) {
                    return !empty($review['comment']);
                });

                $reviewsData[$productId] = [
                    'product' => [
                        'id' => $productId,
                        'name' => $productName,
                        'price' => $productPrice,
                    ],
                    'reviews_count' => count($filteredReviews),
                    'reviews' => $filteredReviews,
                ];

                $productCounter++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'total_products' => $productCounter,
            'data' => $reviewsData,
        ]);
    }
}
