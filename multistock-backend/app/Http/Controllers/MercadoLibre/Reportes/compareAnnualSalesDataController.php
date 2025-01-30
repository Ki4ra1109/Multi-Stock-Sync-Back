<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
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

        // Get query parameters for the two years to compare
        $year1 = request()->query('year1');
        $year2 = request()->query('year2');

        // Validate query parameters
        if (!$year1 || !$year2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los parámetros de consulta year1 y year2 son obligatorios.',
            ], 400);
        }

        // Calculate date range for the two years
        $dateFrom1 = "{$year1}-01-01T00:00:00.000-00:00";
        $dateTo1 = "{$year1}-12-31T23:59:59.999-00:00";
        $dateFrom2 = "{$year2}-01-01T00:00:00.000-00:00";
        $dateTo2 = "{$year2}-12-31T23:59:59.999-00:00";

        // Function to fetch paginated data
        $fetchPaginatedData = function ($dateFrom, $dateTo) use ($credentials, $userId) {
            $totalSales = 0;
            $soldProducts = [];
            $offset = 0;
            $limit = 50; // Adjust limit as needed

            do {
                $response = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/orders/search", [
                        'seller' => $userId,
                        'order.status' => 'paid',
                        'order.date_created.from' => $dateFrom,
                        'order.date_created.to' => $dateTo,
                        'offset' => $offset,
                        'limit' => $limit,
                    ]);

                if ($response->failed()) {
                    return [
                        'error' => true,
                        'response' => $response,
                    ];
                }

                $orders = $response->json()['results'];
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

                $offset += $limit;
            } while (count($orders) === $limit);

            return [
                'error' => false,
                'total_sales' => $totalSales,
                'sold_products' => $soldProducts,
            ];
        };

        // Fetch data for the first year
        $data1 = $fetchPaginatedData($dateFrom1, $dateTo1);
        if ($data1['error']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $data1['response']->json(),
            ], $data1['response']->status());
        }

        // Fetch data for the second year
        $data2 = $fetchPaginatedData($dateFrom2, $dateTo2);
        if ($data2['error']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $data2['response']->json(),
            ], $data2['response']->status());
        }

        // Determine increase or decrease
        $difference = $data2['total_sales'] - $data1['total_sales'];
        $percentageChange = $data1['total_sales'] > 0 ? ($difference / $data1['total_sales']) * 100 : 0;

        // Return comparison data
        return response()->json([
            'status' => 'success',
            'message' => 'Comparación de ventas obtenida con éxito.',
            'data' => [
                'year1' => [
                    'year' => $year1,
                    'total_sales' => $data1['total_sales'],
                    'sold_products' => $data1['sold_products'],
                ],
                'year2' => [
                    'year' => $year2,
                    'total_sales' => $data2['total_sales'],
                    'sold_products' => $data2['sold_products'],
                ],
                'difference' => $difference,
                'percentage_change' => $percentageChange,
            ],
        ]);
    }
    
}