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
            ->get('https://api.mercadolibre.com/shipments/search?seller={$credentials->user_id}&status=to_be_received');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $shipments = $response->json()['results'];
        $productsToReceive = [];

        foreach ($shipments as $shipment) {
            foreach ($shipment['items'] as $item) {
                $productId = $item['item']['id'];
                if (!isset($productsToReceive[$productId])) {
                    $productsToReceive[$productId] = [
                        'id' => $productId,
                        'title' => $item['item']['title'],
                        'quantity' => 0,
                    ];
                }
                $productsToReceive[$productId]['quantity'] += $item['quantity'];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Productos disponibles por recepción obtenidos con éxito.',
            'data' => array_values($productsToReceive),
        ]);
    }
}
