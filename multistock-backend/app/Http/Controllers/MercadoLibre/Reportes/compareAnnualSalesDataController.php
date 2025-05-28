<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class compareAnnualSalesDataController
{
    /**
     * Compare sales data between two years.
     */
    public function compareAnnualSalesData($clientId)
    {
        // Get credentials by client_id
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

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
            // Check if token is expired
            $data = $refreshResponse->json();
            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }

        // Get user id from token
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');
        if ($userResponse->failed()) {
            Log::error("Failed to get user ID for client_id: $clientId. URL: " . request()->fullUrl());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Valide su token.',
                'error' => $userResponse->json(),
            ], 500);
        }
        $userId = $userResponse->json()['id'];

        // Get query parameters for the two years to compare
        $year1 = request()->query('year1');
        $year2 = request()->query('year2');
        // Validate query parameters
        if (!$year1 || !$year2) {
            Log::error("Missing year1 or year2 query parameters. URL: " . request()->fullUrl());
            return response()->json([
                'status' => 'error',
                'message' => 'Los parámetros de consulta year1 y year2 son obligatorios.',
            ], 400);
        }

        // Function to fetch data in parallel by month
        $fetchAnnualData = function ($year) use ($credentials, $userId) {
            $client = new Client([
                'headers' => [
                    'Authorization' => 'Bearer ' . $credentials->access_token,
                ],
                'timeout' => 30,
            ]);
            $promises = [];
            for ($month = 1; $month <= 12; $month++) {
                $from = sprintf('%s-%02d-01T00:00:00.000-00:00', $year, $month);
                $to = date('Y-m-t\T23:59:59.999-00:00', strtotime("$year-$month-01"));
                $promises[] = $client->getAsync('https://api.mercadolibre.com/orders/search', [
                    'query' => [
                        'seller' => $userId,
                        'order.status' => 'paid',
                        'order.date_created.from' => $from,
                        'order.date_created.to' => $to,
                        'limit' => 50,
                        'offset' => 0,
                    ]
                ]);
            }
            try {
                $results = Promise\Utils::unwrap($promises);
            } catch (\Exception $e) {
                Log::error("Error fetching annual data for year $year");
                return [
                    'error' => true,
                    'total_sales' => 0,
                    'sold_products' => [],
                    'total_orders' => 0,
                ];
            }

            $totalSales = 0;
            $soldProducts = [];
            $ordersCount = 0;

            foreach ($results as $response) {
                if ($response->getStatusCode() !== 200) {
                    Log::error("API response not 200 for year $year");
                    continue;
                }
                $data = json_decode($response->getBody()->getContents(), true);
                $orders = $data['results'] ?? [];
                $ordersCount += count($orders);
                foreach ($orders as $order) {
                    $totalSales += $order['total_amount'];
                    foreach ($order['order_items'] as $item) {
                        $soldProducts[] = [
                            'order_id' => $order['id'],
                            'order_date' => $order['date_created'],
                            'title' => $item['item']['title'],
                            'quantity' => $item['quantity'],
                            'price' => $item['unit_price'],
                        ];
                    }
                }
            }

            return [
                'error' => false,
                'total_sales' => $totalSales,
                'sold_products' => $soldProducts,
                'total_orders' => $ordersCount,
            ];
        };
        // Fetch data for both years
        $data1 = $fetchAnnualData($year1);
        $data2 = $fetchAnnualData($year2);

        if ($data1['error'] || $data2['error']) {
            Log::error("Error fetching data for one or both years");
        }

        // 
        $difference = abs($data2['total_sales'] - $data1['total_sales']);

        if ($data1['total_sales'] > $data2['total_sales']) {
            $mostSoldYear = $year1;
            $mostSoldAmount = $data1['total_sales'];
            $leastSoldYear = $year2;
            $leastSoldAmount = $data2['total_sales'];
            $percentageChange = $mostSoldAmount > 0
                ? round(($difference / $mostSoldAmount) * 100, 2)
                : 0;
        } elseif ($data2['total_sales'] > $data1['total_sales']) {
            $mostSoldYear = $year2;
            $mostSoldAmount = $data2['total_sales'];
            $leastSoldYear = $year1;
            $leastSoldAmount = $data1['total_sales'];
            $percentageChange = $mostSoldAmount > 0
                ? round(($difference / $mostSoldAmount) * 100, 2)
                : 0;
        } else {
            $mostSoldYear = 'Igual';
            $mostSoldAmount = $data1['total_sales'];
            $leastSoldYear = 'Igual';
            $leastSoldAmount = $data1['total_sales'];
            $percentageChange = 0;
        }

        // Combine all sold products from both years
        $allSoldProducts = array_merge($data1['sold_products'], $data2['sold_products']);

        // Return comparison data
        return response()->json([
            'status' => 'success',
            'message' => 'Comparación de ventas obtenida con éxito.',
            'data' => [
                'year1' => [
                    'year' => $year1,
                    'total_sales' => $data1['total_sales'],
                    'total_orders' => $data1['total_orders'],
                    'sold_products' => $data1['sold_products'],
                ],
                'year2' => [
                    'year' => $year2,
                    'total_sales' => $data2['total_sales'],
                    'total_orders' => $data2['total_orders'],
                    'sold_products' => $data2['sold_products'],
                ],
                'all_sold_products' => $allSoldProducts,
                'difference' => $difference,
                'percentage_change' => $percentageChange,
                'most_sold_year' => $mostSoldYear,
                'most_sold_amount' => $mostSoldAmount,
                'least_sold_year' => $leastSoldYear,
                'least_sold_amount' => $leastSoldAmount,
            ],
        ]);
    }
}
