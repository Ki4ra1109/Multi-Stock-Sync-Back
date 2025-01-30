<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
{

    /**
     * Get daily sales from MercadoLibre API using client_id.
    */
    public function getDailySales($clientId)
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

        // Get query parameters for date
        $date = request()->query('date', date('Y-m-d')); // Default to current date

        // Calculate date range for the specified date
        $dateFrom = "{$date}T00:00:00.000-00:00";
        $dateTo = "{$date}T23:59:59.999-00:00";

        // API request to get sales for the specified date
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
        $totalSales = 0;
        $soldProducts = [];

        foreach ($orders as $order) {
            $totalSales += $order['total_amount'];

            // Extract sold products (titles and quantities)
            foreach ($order['order_items'] as $item) {
                $soldProducts[] = [
                    'order_id' => $order['id'], // MercadoLibre Order ID
                    'order_date' => $order['date_created'], // Order date
                    'title' => $item['item']['title'], // Product title
                    'quantity' => $item['quantity'],  // Quantity sold
                    'price' => $item['unit_price'],   // Price per unit
                ];
            }
        }

        // Return sales by date data, including sold products
        return response()->json([
            'status' => 'success',
            'message' => 'Ventas diarias obtenidas con éxito.',
            'data' => [
                'date' => $date,
                'total_sales' => $totalSales,
                'sold_products' => $soldProducts, // List of sold products
            ],
        ]);
    }
    
}