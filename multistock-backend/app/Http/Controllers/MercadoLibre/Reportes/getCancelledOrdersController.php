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

        $userResponse = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }

        $userId = $userResponse->json()['id'];
        $dateFrom = request()->query('date_from', date('Y-m-01'));
        $dateTo = request()->query('date_to', date('Y-m-t'));

        $ordersData = [];
        $offset = 0;
        $limit = 50;

        do {
            $params = [
                'seller' => $userId,
                'order.status' => 'cancelled',
                'order.date_created.from' => "{$dateFrom}T00:00:00.000-00:00",
                'order.date_created.to' => "{$dateTo}T23:59:59.999-00:00",
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
            'message' => 'Órdenes canceladas simplificadas obtenidas con éxito.',
            'orders' => $ordersData,
        ]);
    }
}
