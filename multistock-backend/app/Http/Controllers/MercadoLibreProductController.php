<?php

namespace App\Http\Controllers;

use App\Models\MercadoLibreCredential;
use App\Models\MercadoLibreToken;
use Illuminate\Support\Facades\Http;

class MercadoLibreProductController extends Controller
{
    /**
     * Get products from MercadoLibre API.
     */
    public function listProducts()
    {
        // Get token in database
        $token = MercadoLibreToken::find(1);

        // Check if token exists
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró un token válido. Por favor, inicie sesión primero.',
            ], 404);
        }

        // Get user id from token
        $userId = $this->getUserIdFromToken($token->access_token);

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
            ], 500);
        }

        // Get query parameters
        $limit = request()->query('limit', 50); // Default limit to 50
        $offset = request()->query('offset', 0); // Default offset to 0

        // API request to get products with limit and offset
        $response = Http::withToken($token->access_token)
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

    /**
     * Extract user_id from MercadoLibre access token.
     */
    private function getUserIdFromToken(string $accessToken): ?string
    {
        // Make request to get user info
        $response = Http::withToken($accessToken)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->ok() && isset($response->json()['id'])) {
            return (string) $response->json()['id'];
        }

        return null;
    }
}
