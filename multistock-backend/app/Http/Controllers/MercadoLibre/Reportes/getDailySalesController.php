<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getDailySalesController
{
    public function getDailySales($clientId)
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
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $userId = $response->json()['id'];

        $date = request()->query('date', date('Y-m-d'));
        $dateFrom = "{$date}T00:00:00.000-00:00";
        $dateTo = "{$date}T23:59:59.999-00:00";

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid&order.date_created.from={$dateFrom}&order.date_created.to={$dateTo}");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $totalSales = 0;
        $soldProducts = [];

        foreach ($orders as $order) {
            $totalSales += $order['total_amount'];

            foreach ($order['order_items'] as $item) {
                $title = $item['item']['title'];
                $thumbnail = $item['item']['thumbnail'] ?? null;

                // Agrupar por título
                if (!isset($soldProducts[$title])) {
                    $soldProducts[$title] = [
                        'title' => $title,
                        'quantity' => 0,
                        'total_amount' => 0,
                        'thumbnail' => $thumbnail,
                    ];
                }

                $soldProducts[$title]['quantity'] += $item['quantity'];
                $soldProducts[$title]['total_amount'] += $item['quantity'] * $item['unit_price'];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Ventas diarias obtenidas con éxito.',
            'data' => [
                'date' => $date,
                'total_sales' => $totalSales,
                'sold_products' => array_values($soldProducts), // devolver como lista
            ],
        ]);
    }
}
    
