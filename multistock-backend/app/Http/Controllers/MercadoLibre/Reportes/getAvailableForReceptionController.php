<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getAvailableForReceptionController
{
    public function getAvailableForReception($clientId)
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
                'message' => 'No se pudo obtener el ID del usuario. Valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $userId = $response->json()['id'];

        
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/shipments/search?seller={$userId}&status=to_be_received");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $shipments = $response->json()['results'];

        
        if (empty($shipments)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron envíos pendientes de recepción.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Envíos pendientes de recepción obtenidos con éxito.',
            'data' => $shipments,
        ]);
    }
}
