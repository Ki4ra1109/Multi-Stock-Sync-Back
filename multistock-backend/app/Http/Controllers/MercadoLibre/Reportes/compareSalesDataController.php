<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class compareSalesDataController
{

    public function compareSalesData($clientId)
    {
        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $clientId;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
            return MercadoLibreCredential::where('client_id', $clientId)->first();
        });

        if (Cache::has($cacheKey)) {
            Log::info("Credenciales Mercado Libre obtenidas desde cache para client_id: $clientId");
        } else {
            Log::info("Credenciales Mercado Libre cacheadas por primera vez para client_id: $clientId");
        }

        // Check if credentials exist
        if (!$credentials) {
            Log::error("No credentials found for client_id: $clientId");
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Refresh token if expired
        if ($credentials->isTokenExpired()) {

            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials->client_id,
                'client_secret' => $credentials->client_secret,
                'refresh_token' => $credentials->refresh_token,
            ]);
            if ($refreshResponse->failed()) {
                Log::error("Token refresh failed for client_id: $clientId");
                return response()->json(['error' => 'No se pudo refrescar el token'], 401);
            }
            $data = $refreshResponse->json();
            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }

        // Get user id from token
        $Response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');
        if ($Response->failed()) {
            Log::error("Failed to get user ID for client_id: $clientId. URL: " . request()->fullUrl());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Valide su token.',
                'error' => $Response->json(),
            ], 500);
        }
        $userId = $Response->json()['id'];

        // Get query parameters
        $month1 = request()->query('month1');
        $year1 = request()->query('year1');
        $month2 = request()->query('month2');
        $year2 = request()->query('year2');

        

        
        // Validate query parameters
        if (!$month1 || !$year1 || !$month2 || !$year2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los parámetros de consulta month1, year1, month2 y year2 son obligatorios.',
            ], 400);
        }

        // Date ranges
        $dateFrom1 = "{$year1}-{$month1}-01T00:00:00.000-00:00";
        $dateTo1 = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom1));
        $dateFrom2 = "{$year2}-{$month2}-01T00:00:00.000-00:00";
        $dateTo2 = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom2));

        // Get paginated orders
        $orders1 = $this->getPaginatedOrders($credentials->access_token, $userId, $dateFrom1, $dateTo1);
        $orders2 = $this->getPaginatedOrders($credentials->access_token, $userId, $dateFrom2, $dateTo2);

        $totalSales1 = 0;
        $totalSales2 = 0;
        $soldProducts1 = [];
        $soldProducts2 = [];

        foreach ($orders1 as $order) {
            $totalSales1 += $order['total_amount'];
            foreach ($order['order_items'] as $item) {
                $soldProducts1[] = [
                    'order_id' => $order['id'],
                    'order_date' => $order['date_created'],
                    'title' => $item['item']['title'],
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price'],
                ];
            }
        }

        foreach ($orders2 as $order) {
            $totalSales2 += $order['total_amount'];
            foreach ($order['order_items'] as $item) {
                $soldProducts2[] = [
                    'order_id' => $order['id'],
                    'order_date' => $order['date_created'],
                    'title' => $item['item']['title'],
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price'],
                ];
            }
        }

        // Comparison
        $olderMonthSales = $totalSales1;
        $newerMonthSales = $totalSales2;
        if (strtotime("{$year1}-{$month1}-01") > strtotime("{$year2}-{$month2}-01")) {
            $olderMonthSales = $totalSales2;
            $newerMonthSales = $totalSales1;
        }

        $difference = $newerMonthSales - $olderMonthSales;
        $percentageChange = 0;
        if ($olderMonthSales > 0) {
            $percentageChange = ($difference / $olderMonthSales) * 100;
        } elseif ($newerMonthSales > 0) {
            $percentageChange = 100;
        }
        $percentageChange = round($percentageChange, 2);

        return response()->json([
            'status' => 'success',
            'message' => 'Comparación de ventas obtenida con éxito.',
            'data' => [
                'month1' => [
                    'year' => $year1,
                    'month' => $month1,
                    'total_sales' => $totalSales1,
                    'total_orders' => count($orders1),
                    'sold_products' => $soldProducts1,
                ],
                'month2' => [
                    'year' => $year2,
                    'month' => $month2,
                    'total_sales' => $totalSales2,
                    'total_orders' => count($orders2),
                    'sold_products' => $soldProducts2,
                ],
                'difference' => $difference,
                'percentage_change' => $percentageChange,



            ],
        ]);
    }

    /**
     * Get all orders with pagination
     */
    private function getPaginatedOrders($token, $userId, $dateFrom, $dateTo)
    {
        $orders = [];
        $offset = 0;
        $limit = 50;

        do {
            $response = Http::withToken($token)->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'order.date_created.from' => $dateFrom,
                'order.date_created.to' => $dateTo,
                'offset' => $offset,
                'limit' => $limit,
            ]);

            if ($response->failed()) {
                break; // Salimos del bucle si falla
            }

            $batch = $response->json()['results'] ?? [];
            $orders = array_merge($orders, $batch);
            $offset += $limit;
        } while (count($batch) === $limit);

        return $orders;
    }
}
