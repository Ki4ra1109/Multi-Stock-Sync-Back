<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class compareSalesDataController
{

    /**
     *  Compare sales data between two months
    */

    public function compareSalesData($clientId)
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

        // Get query parameters for the two months to compare
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

        // Calculate date range for the two months
        $dateFrom1 = "{$year1}-{$month1}-01T00:00:00.000-00:00";
        $dateTo1 = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom1));
        $dateFrom2 = "{$year2}-{$month2}-01T00:00:00.000-00:00";
        $dateTo2 = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom2));

        // API request to get sales for the two months
        $response1 = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid&order.date_created.from={$dateFrom1}&order.date_created.to={$dateTo1}");

        $response2 = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid&order.date_created.from={$dateFrom2}&order.date_created.to={$dateTo2}");

        // Validate responses
        if ($response1->failed() || $response2->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response1->failed() ? $response1->json() : $response2->json(),
            ], $response1->failed() ? $response1->status() : $response2->status());
        }

        // Process sales data
        $orders1 = $response1->json()['results'];
        $orders2 = $response2->json()['results'];

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

        // Ensure month1 is the older month and month2 is the newer month
        $olderMonthSales = $totalSales1;
        $newerMonthSales = $totalSales2;
        if (strtotime("{$year1}-{$month1}-01") > strtotime("{$year2}-{$month2}-01")) {
            $olderMonthSales = $totalSales2;
            $newerMonthSales = $totalSales1;
        }

        // Determine increase or decrease
        $difference = $newerMonthSales - $olderMonthSales;
        if ($olderMonthSales > 0) {
            $percentageChange = ($difference / $olderMonthSales) * 100;
        } elseif ($newerMonthSales > 0) {
            $percentageChange = 100;
        } else {
            $percentageChange = 0;
        }
        $percentageChange = round($percentageChange, 2);

        // Return comparison data
        return response()->json([
            'status' => 'success',
            'message' => 'Comparación de ventas obtenida con éxito.',
            'data' => [
                'month1' => [
                    'year' => $year1,
                    'month' => $month1,
                    'total_sales' => $totalSales1,
                    'sold_products' => $soldProducts1,
                ],
                'month2' => [
                    'year' => $year2,
                    'month' => $month2,
                    'total_sales' => $totalSales2,
                    'sold_products' => $soldProducts2,
                ],
                'difference' => $difference,
                'percentage_change' => $percentageChange,
            ],
        ]);
    }
    
}