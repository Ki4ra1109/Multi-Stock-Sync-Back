<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getStockRotationController
{
    public function getStockRotation($clientId)
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
            ->get('https://api.mercadolibre.com/orders/search?seller={$credentials->user_id}&order.status=paid');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $stockRotation = [];

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                if (!isset($stockRotation[$productId])) {
                    $stockRotation[$productId] = [
                        'id' => $productId,
                        'title' => $item['item']['title'],
                        'quantity' => 0,
                    ];
                }
                $stockRotation[$productId]['quantity'] += $item['quantity'];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rotación de stock obtenida con éxito.',
            'data' => array_values($stockRotation),
        ]);
    }
}
