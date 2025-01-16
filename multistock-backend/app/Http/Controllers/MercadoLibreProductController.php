<?php

namespace App\Http\Controllers;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;

class MercadoLibreProductController extends Controller
{
    /**
     * Get products from MercadoLibre API using client_id.
     */
    public function listProductsByClientId($clientId)
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

        // Get query parameters
        $limit = request()->query('limit', 50); // Default limit to 50
        $offset = request()->query('offset', 0); // Default offset to 0

        // API request to get products with limit and offset
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                'limit' => $limit,
                'offset' => $offset,
            ]);

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Return products data
        $data = $response->json();

        return response()->json([
            'status' => 'success',
            'message' => 'Productos obtenidos con éxito.',
            'data' => $data,
        ]);
    }

}
