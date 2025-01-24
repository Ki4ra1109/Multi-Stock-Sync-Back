<?php

namespace App\Http\Controllers;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
{
    /**
     * Get invoice report from MercadoLibre API using client_id.
    */

    public function getInvoiceReport($clientId)
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

        // Get query parameters
        $group = request()->query('group', 'MP'); // Default group to 'MP'
        $documentType = request()->query('document_type', 'BILL'); // Default document type to 'BILL'
        $offset = request()->query('offset', 1); // Default offset to 1
        $limit = request()->query('limit', 2); // Default limit to 2

        // API request to get invoice report
        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/billing/integration/monthly/periods', [
                'group' => $group,
                'document_type' => $documentType,
                'offset' => $offset,
                'limit' => $limit,
                'site_id' => 'MLC', // Ensure the site is set to Chile
            ]);

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Return invoice report data
        return response()->json([
            'status' => 'success',
            'message' => 'Reporte de facturas obtenido con éxito.',
            'data' => $response->json(),
        ]);
    }

    /**
     * Get total sales by month from MercadoLibre API using client_id.
    */

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

        // Get query parameters for month and year
        $month = request()->query('month', date('m')); // Default to current month
        $year = request()->query('year', date('Y')); // Default to current year

        // Calculate date range for the specified month and year
        $dateFrom = "{$year}-{$month}-01T00:00:00.000-00:00";
        $dateTo = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom));

        // API request to get sales by month
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
            'message' => 'Ventas por mes obtenidas con éxito.',
            'data' => $salesByMonth,
        ]);
    }

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

    /**
     * Get weeks of the month based on the year and month.
    */
    public function getWeeksOfMonth(Request $request)
    {
        // Get the year and month from the request
        $year = $request->query('year', date('Y')); // Default to current year
        $month = $request->query('month', date('m')); // Default to current month

        // Validate year and month
        if (!checkdate($month, 1, $year)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fecha no válida. Por favor, proporcione un año y mes válidos.',
            ], 400);
        }

        try {
            // First day of the month
            $startOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1);

            // Last day of the month
            $endOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();

            // Get the number of weeks in the month
            $weeks = [];
            $currentStartDate = $startOfMonth;

            // Loop through the month and create weeks
            while ($currentStartDate <= $endOfMonth) {
                $currentEndDate = $currentStartDate->copy()->endOfWeek();

                if ($currentEndDate > $endOfMonth) {
                    $currentEndDate = $endOfMonth; // Adjust if the week goes into the next month
                }

                $weeks[] = [
                    'start_date' => $currentStartDate->toDateString(),
                    'end_date' => $currentEndDate->toDateString(),
                ];

                // Move to the next week
                $currentStartDate = $currentEndDate->addDay();
            }

            // Filter out weeks that are not within the specified month
            $weeks = array_filter($weeks, function ($week) use ($month) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $week['start_date'])->month == $month;
            });

            // Return weeks data
            return response()->json([
                'status' => 'success',
                'message' => 'Semanas obtenidas con éxito.',
                'data' => array_values($weeks),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar la solicitud.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get total sales for a specific week based on the year, month, and week number.
    */

    public function getSalesByWeek(Request $request, $clientId)
    {
        // Get the year, month, week start date, and week end date from the request
        $year = $request->query('year', date('Y')); // Default to current year
        $month = $request->query('month', date('m')); // Default to current month
        $weekStartDate = $request->query('week_start_date'); // Start date of the week
        $weekEndDate = $request->query('week_end_date'); // End date of the week
    
        // Ensure both dates are provided
        if (!$weekStartDate || !$weekEndDate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Las fechas de la semana son requeridas.',
            ], 400);
        }
    
        // Convert to Carbon instances
        $startOfWeek = \Carbon\Carbon::createFromFormat('Y-m-d', $weekStartDate)->startOfDay();
        $endOfWeek = \Carbon\Carbon::createFromFormat('Y-m-d', $weekEndDate)->endOfDay();
    
        // Get credentials and user ID (same as in the other methods)
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
    
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
    
        // Get sales within the specified week date range
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'order.date_created.from' => $startOfWeek->toIso8601String(),
                'order.date_created.to' => $endOfWeek->toIso8601String()
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
    
        // Return sales by week data, including sold products
        return response()->json([
            'status' => 'success',
            'message' => 'Ingresos y productos obtenidos con éxito.',
            'data' => [
                'week_start_date' => $startOfWeek->toDateString(),
                'week_end_date' => $endOfWeek->toDateString(),
                'total_sales' => $totalSales,
                'sold_products' => $soldProducts, // List of sold products
            ],
        ]);
    }

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
