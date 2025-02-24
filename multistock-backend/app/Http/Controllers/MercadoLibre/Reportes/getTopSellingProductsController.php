<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getTopSellingProductsController
{

    /**
     * Get top-selling products from MercadoLibre API using client_id.
    */
    public function getTopSellingProducts($clientId)
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

        // Get query parameters for year and month
        $year = request()->query('year', date('Y')); // Default to current year
        $month = request()->query('month'); // Month is optional

        // Get query parameters for pagination
        $page = request()->query('page', 1); // Default to page 1
        $perPage = request()->query('per_page', 10); // Default to 10 items per page

        // Calculate date range based on the provided year and month
        if ($month) {
            // If month is provided, get the date range for the specified month
            $dateFrom = "{$year}-{$month}-01T00:00:00.000-00:00";
            $dateTo = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom));
        } else {
            // If month is not provided, get the date range for the entire year
            $dateFrom = "{$year}-01-01T00:00:00.000-00:00";
            $dateTo = "{$year}-12-31T23:59:59.999-00:00";
        }

        // API request to get sales within the specified date range
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid&order.date_created.from={$dateFrom}&order.date_created.to={$dateTo}");

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
        $productSales = [];
        $totalSales = 0;

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                if (!isset($productSales[$productId])) {
                    $productSales[$productId] = [
                        'title' => $item['item']['title'],
                        'quantity' => 0,
                        'total_amount' => 0,
                    ];
                }
                $productSales[$productId]['quantity'] += $item['quantity'];
                $productSales[$productId]['total_amount'] += $item['quantity'] * $item['unit_price'];
                $totalSales += $item['quantity'] * $item['unit_price'];
            }
        }

        // Sort products by quantity sold
        usort($productSales, function ($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });

        // Paginate the results
        $totalProducts = count($productSales);
        $totalPages = ceil($totalProducts / $perPage);
        $offset = ($page - 1) * $perPage;
        $productSales = array_slice($productSales, $offset, $perPage);

        // Return top-selling products data with pagination
        return response()->json([
            'status' => 'success',
            'message' => 'Productos más vendidos obtenidos con éxito.',
            'total_sales' => $totalSales,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'data' => $productSales,
        ]);
    }
    
}