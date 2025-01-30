<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
{

    /**
     * Get sales by date range from MercadoLibre API using client_id.
    */

    public function getSalesByDateRange(Request $request, $clientId)
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

        // Get query parameters for date range
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Validate date range
        if (!$startDate || !$endDate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Las fechas inicial y final son requeridas.',
            ], 400);
        }

        // API request to get sales within the specified date range
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'order.date_created.from' => "{$startDate}T00:00:00.000-00:00",
                'order.date_created.to' => "{$endDate}T23:59:59.999-00:00"
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
        $salesData = [];

        foreach ($orders as $order) {
            $orderDate = date('Y-m-d', strtotime($order['date_created']));
            if (!isset($salesData[$orderDate])) {
                $salesData[$orderDate] = [];
            }

            $orderData = [
                'order_id' => $order['id'],
                'order_date' => $order['date_created'],
                'total_amount' => $order['total_amount'],
                'payment_method' => $order['payments'][0]['payment_type'] ?? 'unknown',
                'products' => [],
            ];

            // Extract sold products (titles, quantities, categories, prices, IDs)
            foreach ($order['order_items'] as $item) {
                $productData = [
                    'id' => $item['item']['id'], // Add product ID
                    'title' => $item['item']['title'],
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price'],
                    'category_id' => $item['item']['category_id'],
                ];

                // Get category details
                $categoryResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/categories/{$item['item']['category_id']}");

                if (!$categoryResponse->failed()) {
                    $productData['category'] = $categoryResponse->json()['name'];
                }

                $orderData['products'][] = $productData;
            }

            $salesData[$orderDate][] = $orderData;
        }

        // Return sales data grouped by day
        return response()->json([
            'status' => 'success',
            'message' => 'Ventas obtenidas con éxito.',
            'data' => $salesData,
        ]);
    }
    
}