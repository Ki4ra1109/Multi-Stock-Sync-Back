<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getProductReviewsController extends Controller
{
    protected $mercadoLibreQueries;

    public function __construct()
    {
        // No need for MercadoLibreQueries dependency
    }

    /**
     * Get product reviews from MercadoLibre API using product_id.
     */
    public function getProductReviews($productId, Request $request)
    {
        $clientId = $request->query('client_id');

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

        // API request to get product reviews
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/reviews/item/{$productId}");

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Return product reviews data
        return response()->json([
            'status' => 'success',
            'message' => 'Opiniones obtenidas con éxito.',
            'data' => $response->json(),
        ]);
    }
}