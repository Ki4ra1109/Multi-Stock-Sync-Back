<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
{

    /**
     * Get total sales for the entire year from MercadoLibre API using client_id.
    */

    public function getAnnualSales($clientId)
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

        // Get query parameter for year
        $year = request()->query('year', date('Y')); // Default to current year

        // Calculate date range for the entire year
        $dateFrom = "{$year}-01-01T00:00:00.000-00:00";
        $dateTo = "{$year}-12-31T23:59:59.999-00:00";

        // API request to get sales for the entire year
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
        $salesByMonth = [];

        foreach ($orders as $order) {
            $month = date('Y-m', strtotime($order['date_created']));
            if (!isset($salesByMonth[$month])) {
            $salesByMonth[$month] = [
                'total_amount' => 0,
                'orders' => []
            ];
            }
            $salesByMonth[$month]['total_amount'] += $order['total_amount'];
            $salesByMonth[$month]['orders'][] = [
            'id' => $order['id'],
            'date_created' => $order['date_created'],
            'total_amount' => $order['total_amount'],
            'status' => $order['status'],
            'sold_products' => []
            ];

            // Extract sold products (titles and quantities)
            foreach ($order['order_items'] as $item) {
            $salesByMonth[$month]['orders'][count($salesByMonth[$month]['orders']) - 1]['sold_products'][] = [
                'order_id' => $order['id'], // MercadoLibre Order ID
                'order_date' => $order['date_created'], // Order date
                'title' => $item['item']['title'], // Product title
                'quantity' => $item['quantity'],  // Quantity sold
                'price' => $item['unit_price'],   // Price per unit
            ];
            }
        }

        // Return sales by month data
        return response()->json([
            'status' => 'success',
            'message' => 'Ventas anuales obtenidas con éxito.',
            'data' => $salesByMonth,
        ]);
    }

}
