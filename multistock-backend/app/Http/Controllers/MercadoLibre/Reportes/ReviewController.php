<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class ReviewController
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
                $productPrice = $item['unit_price'];

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
                        'price' => $productPrice,
                    ],
                    'reviews' => array_filter(array_map(function ($review) {
                        return [
                            'rating' => $review['rating'] ?? null,
                            'comment' => $review['content'] ?? null,
                            'full_review' => $review['content'] ? $review : null,
                        ];
                    }, $reviews['reviews'] ?? []), function ($review) {
                        return !empty($review['comment']);
                    }),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'data' => $reviewsData,
        ]);
    }
}