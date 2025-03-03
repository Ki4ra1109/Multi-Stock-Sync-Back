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
    public function getReviewsByClientId($clientId, $productId)
    {
        // Get credentials by client_id
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        // Check if credentials exist
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Check if token is expired
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        // Get user id from token
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

        // Get product details to identify the seller_id
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

        // Check if seller_id is available
        if (!$sellerId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró el seller_id del producto.',
            ], 400);
        }

        // Get reviews for the product using the seller's access token
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

        // Return reviews data
        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'data' => $reviews,
        ]);
    }

    /**
     * Get reviews from MercadoLibre API using client_id for multiple products.
     */
    public function getBatchReviewsByClientId($clientId, Request $request)
    {
        $productIds = $request->input('product_ids', []);
        $limit = $request->input('limit', 5);
        $offset = $request->input('offset', 0);

        // Get credentials by client_id
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        // Check if credentials exist
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Check if token is expired
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        $reviewsData = [];
        $totalReviews = 0;

        foreach ($productIds as $productId) {
            // Get product details to identify the seller_id
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

            // Check if seller_id is available
            if (!$sellerId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró el seller_id del producto.',
                ], 400);
            }

            // Get reviews for the product using the seller's access token
            $reviewResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/reviews/item/{$productId}?access_token={$credentials->access_token}&limit={$limit}&offset={$offset}");

            if ($reviewResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudieron obtener las reviews.',
                    'error' => $reviewResponse->json(),
                ], 500);
            }

            $reviews = $reviewResponse->json();
            $reviewsData[$productId] = $reviews['reviews'] ?? [];
            $totalReviews += count($reviews['reviews'] ?? []);
        }

        // Return reviews data
        return response()->json([
            'status' => 'success',
            'message' => 'Reviews obtenidas con éxito.',
            'data' => [
                'paging' => [
                    'total' => $totalReviews,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
                'reviews' => $reviewsData,
            ],
        ]);
    }
}
