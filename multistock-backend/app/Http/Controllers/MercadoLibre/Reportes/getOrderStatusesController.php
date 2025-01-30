<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
{

    /**
     * Get order statuses (paid, pending, canceled) from MercadoLibre API using client_id.
    */
    public function getOrderStatuses($clientId)
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

        // API request to get order statuses
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}");

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Process order statuses
        $orders = $response->json()['results'];
        $statuses = [
            'paid' => 0,
            'pending' => 0,
            'canceled' => 0,
        ];

        foreach ($orders as $order) {
            if (isset($statuses[$order['status']])) {
                $statuses[$order['status']]++;
            }
        }

        // Return order statuses data
        return response()->json([
            'status' => 'success',
            'message' => 'Estados de órdenes obtenidos con éxito.',
            'data' => $statuses,
        ]);
    }
    
}