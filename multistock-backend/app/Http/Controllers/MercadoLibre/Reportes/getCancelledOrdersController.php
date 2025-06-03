<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getCancelledOrdersController
{
    public function getCancelledOrders($clientId)
    {
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
        error_log("credentials " . json_encode($credentials));
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }
        try {
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);
                // Si la solicitud falla, devolver un mensaje de error
                if ($refreshResponse->failed()) {
                    return response()->json(['error' => 'No se pudo refrescar el token'], 401);
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token: ' . $e->getMessage(),
            ], 500);
        }

        $userResponse = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }

        $userId = $userResponse->json()['id'];

        $ordersData = [];
        $offset = 0;
        $limit = 50;

        do {
            $params = [
                'seller' => $userId,
                'order.status' => 'cancelled',
                'limit' => $limit,
                'offset' => $offset
            ];

            $response = Http::withToken($credentials->access_token)->get("https://api.mercadolibre.com/orders/search", $params);

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al consultar órdenes canceladas.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $data = $response->json();
            $offset += $limit;

            foreach ($data['results'] as $order) {
                foreach ($order['order_items'] as $item) {
                    $ordersData[] = [
                        'id' => $order['id'],
                        'created_date' => $order['date_created'],
                        'total_amount' => $order['total_amount'],
                        'status' => $order['status'],
                        'product' => [
                            'title' => $item['item']['title'] ?? null,
                            'quantity' => $item['quantity'] ?? null,
                            'price' => $item['unit_price'] ?? null
                        ]
                    ];
                }
            }

        } while ($offset < ($data['paging']['total'] ?? 0));

        return response()->json([
            'status' => 'success',
            'message' => 'Órdenes canceladas obtenidas con éxito.',
            'orders' => $ordersData,
        ]);
    }
}
