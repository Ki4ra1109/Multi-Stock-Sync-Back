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
    public function getReviewsByClientId($clientId)
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

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $reviewsData = [];

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $productName = $item['item']['title'];

                $reviewResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/reviews/item/{$productId}?access_token={$credentials->access_token}");

                if ($reviewResponse->failed()) {
                    continue;
                }

                $reviews = $reviewResponse->json();
                $reviewsData[$productId] = [
                    'product' => [
                        'id' => $productId,
                        'name' => $productName,
                    ],
                    'reviews' => array_map(function ($review) {
                        return [
                            'rating' => $review['rating'] ?? null,
                            'comment' => $review['content'] ?? null,
                            'full_review' => $review['content'] ? $review : null,
                        ];
                    }, $reviews['reviews'] ?? []),
                ];
            }
        }

        // Filter reviews to show all data for those with comments, and only rating and name for those without comments
        foreach ($reviewsData as $productId => &$productReviews) {
            $productReviews['reviews'] = array_map(function ($review) {
                if ($review['comment']) {
                    return $review['full_review'];
                } else {
                    return [
                        'rating' => $review['rating'],
                        'name' => $review['name'],
                    ];
                }
            }, $productReviews['reviews']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'data' => $reviewsData,
        ]);
    }
}
