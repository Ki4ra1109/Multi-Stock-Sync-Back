<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getSalesByMonthController
{

    public function getSalesByMonth($clientId)
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
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => env('MELI_CLIENT_ID'),
                'client_secret' => env('MELI_CLIENT_SECRET'),
                'refresh_token' => $credentials->refresh_token,
            ]);

            if ($refreshResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El token ha expirado y no se pudo refrescar',
                    'error' => $refreshResponse->json(),
                ], 401);
            }

            $newTokenData = $refreshResponse->json();
            $credentials->access_token = $newTokenData['access_token'];
            $credentials->refresh_token = $newTokenData['refresh_token'] ?? $credentials->refresh_token;
            $credentials->expires_in = $newTokenData['expires_in'];
            $credentials->updated_at = now();
            $credentials->save();
        }

        $userResponse = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');

        // If it fails by token, try refreshing again
        if ($userResponse->status() === 401) {
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => env('MELI_CLIENT_ID'),
                'client_secret' => env('MELI_CLIENT_SECRET'),
                'refresh_token' => $credentials->refresh_token,
            ]);

            if ($refreshResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El token ha expirado y no se pudo refrescar',
                    'error' => $refreshResponse->json(),
                ], 401);
            }

            $newTokenData = $refreshResponse->json();
            $credentials->access_token = $newTokenData['access_token'];
            $credentials->refresh_token = $newTokenData['refresh_token'] ?? $credentials->refresh_token;
            $credentials->expires_in = $newTokenData['expires_in'];
            $credentials->updated_at = now();
            $credentials->save();

            // Retry the request
            $userResponse = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');

            if ($userResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                    'error' => $userResponse->json(),
                ], 500);
            }
        }

        $userId = $userResponse->json()['id'];

        // Get query parameters for month and year
        $month = request()->query('month', date('m')); // Default to current month
        $year = request()->query('year', date('Y')); // Default to current year

        // Ensure month has leading zero if needed
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);

        // Calculate date range for the specified month and year
        $timezone = '-04:00'; // Ajusta según la zona horaria de tus órdenes
        $dateFrom = "{$year}-{$month}-01T00:00:00.000{$timezone}";
        $dateTo = date("Y-m-t\T23:59:59.999{$timezone}", strtotime($dateFrom));

        $offset = 0;
        $limit = 50;

        // Initialize the specific month key
        $requestedMonth = "{$year}-{$month}";
        $salesByMonth = [
            $requestedMonth => [
                'total_amount' => 0,
                'orders' => []
            ]
        ];

        // API request to get sales by month
        do {
            $response = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/orders/search", [
                    "seller" => $userId,
                    'order.status' => 'paid',
                    'order.date_created.from' => $dateFrom,
                    'order.date_created.to' => $dateTo,
                    'offset' => $offset,
                    'limit' => $limit,
                ]);

            // Validate response
            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al conectar con la API de MercadoLibre.',
                    'error' => $response->json(),
                ], $response->status());
            }

            // Process sales data
            $orders = $response->json()['results'];

            foreach ($orders as $order) {
                // Verificar que la orden realmente pertenece al mes solicitado
                $orderMonth = date('Y-m', strtotime($order['date_created']));

                // Solo procesar si la orden pertenece exactamente al mes solicitado
                if ($orderMonth === $requestedMonth) {
                    $salesByMonth[$requestedMonth]['total_amount'] += $order['total_amount'];

                    $orderData = [
                        'id' => $order['id'],
                        'date_created' => $order['date_created'],
                        'total_amount' => $order['total_amount'],
                        'status' => $order['status'],
                        'sold_products' => []
                    ];

                    // Extract sold products (titles and quantities)
                    foreach ($order['order_items'] as $item) {
                        $orderData['sold_products'][] = [
                            'order_id' => $order['id'], // MercadoLibre Order ID
                            'order_date' => $order['date_created'], // Order date
                            'title' => $item['item']['title'], // Product title
                            'quantity' => $item['quantity'],  // Quantity sold
                            'price' => $item['unit_price'],   // Price per unit
                        ];
                    }

                    $salesByMonth[$requestedMonth]['orders'][] = $orderData;
                }
            }

            $offset += $limit;
        } while (count($orders) == $limit);

        // Return sales by month data
        return response()->json([
            'status' => 'success',
            'message' => 'Ventas por mes obtenidas con éxito.',
            'data' => $salesByMonth,
            'period' => [
                'month' => $month,
                'year' => $year,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    }
}
