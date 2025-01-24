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

        // API request to get all sales
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid");

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
            }
        }

        // Sort products by quantity sold
        usort($productSales, function ($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });

        // Return top-selling products data
        return response()->json([
            'status' => 'success',
            'message' => 'Productos más vendidos obtenidos con éxito.',
            'data' => $productSales,
        ]);
    }

    /**
     * Get order statuses (paid, pending, canceled) from MercadoLibre API using client_id.
    */
    public function getOrderStatuses($clientId)
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

        // API request to get order statuses
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}");

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Process order statuses
        $orders = $response->json()['results'];
        $statuses = [
            'paid' => 0,
            'pending' => 0,
            'canceled' => 0,
        ];

        foreach ($orders as $order) {
            if (isset($statuses[$order['status']])) {
                $statuses[$order['status']]++;
            }
        }

        // Return order statuses data
        return response()->json([
            'status' => 'success',
            'message' => 'Estados de órdenes obtenidos con éxito.',
            'data' => $statuses,
        ]);
    }

    /**
     * Get top payment methods from MercadoLibre API using client_id.
    */
    public function getTopPaymentMethods($clientId)
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

        // API request to get all sales
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid");

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Process payment methods data
        $orders = $response->json()['results'];
        $paymentMethods = [];

        foreach ($orders as $order) {
            foreach ($order['payments'] as $payment) {
                $method = $payment['payment_type'];
                if (!isset($paymentMethods[$method])) {
                    $paymentMethods[$method] = 0;
                }
                $paymentMethods[$method]++;
            }
        }

        // Sort payment methods by usage
        arsort($paymentMethods);

        // Return top payment methods data
        return response()->json([
            'status' => 'success',
            'message' => 'Métodos de pago más utilizados obtenidos con éxito.',
            'data' => $paymentMethods,
        ]);
    }

    /**
     * Get a general summary of the store.
    */
    public function summary($clientId)
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

        // Get total sales
        $totalSalesResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid");

        if ($totalSalesResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las ventas totales.',
                'error' => $totalSalesResponse->json(),
            ], $totalSalesResponse->status());
        }

        $orders = $totalSalesResponse->json()['results'];
        $totalSales = 0;
        foreach ($orders as $order) {
            $totalSales += $order['total_amount'];
        }

        // Get top-selling products (limit to 5)
        $topSellingProductsResponse = $this->getTopSellingProducts($clientId);
        if ($topSellingProductsResponse->getStatusCode() !== 200) {
            return $topSellingProductsResponse;
        }
        $topSellingProducts = array_slice($topSellingProductsResponse->getData(true)['data'], 0, 5);

        // Get order statuses
        $orderStatusesResponse = $this->getOrderStatuses($clientId);
        if ($orderStatusesResponse->getStatusCode() !== 200) {
            return $orderStatusesResponse;
        }
        $orderStatuses = $orderStatusesResponse->getData(true)['data'];

        // Get daily sales (summary only)
        $dailySalesResponse = $this->getDailySales($clientId);
        if ($dailySalesResponse->getStatusCode() !== 200) {
            return $dailySalesResponse;
        }
        $dailySales = $dailySalesResponse->getData(true)['data']['total_sales'];

        // Get weekly sales (summary only)
        $currentWeekStart = \Carbon\Carbon::now()->startOfWeek()->toDateString();
        $currentWeekEnd = \Carbon\Carbon::now()->endOfWeek()->toDateString();
        $weeklySalesResponse = $this->getSalesByWeek(new Request([
            'week_start_date' => $currentWeekStart,
            'week_end_date' => $currentWeekEnd
        ]), $clientId);
        if ($weeklySalesResponse->getStatusCode() !== 200) {
            return $weeklySalesResponse;
        }
        $weeklySales = $weeklySalesResponse->getData(true)['data']['total_sales'];

        // Get monthly sales (summary only)
        $monthlySalesResponse = $this->getSalesByMonth($clientId);
        if ($monthlySalesResponse->getStatusCode() !== 200) {
            return $monthlySalesResponse;
        }
        $monthlySales = array_sum(array_column($monthlySalesResponse->getData(true)['data'], 'total_amount'));

        // Get annual sales (summary only)
        $annualSalesResponse = $this->getAnnualSales($clientId);
        if ($annualSalesResponse->getStatusCode() !== 200) {
            return $annualSalesResponse;
        }
        $annualSales = array_sum(array_column($annualSalesResponse->getData(true)['data'], 'total_amount'));

        // Get top payment methods (limit to 3)
        $topPaymentMethodsResponse = $this->getTopPaymentMethods($clientId);
        if ($topPaymentMethodsResponse->getStatusCode() !== 200) {
            return $topPaymentMethodsResponse;
        }
        $topPaymentMethods = array_slice($topPaymentMethodsResponse->getData(true)['data'], 0, 3);

        // Return summary data
        return response()->json([
            'status' => 'success',
            'message' => 'Resumen de la tienda obtenido con éxito.',
            'data' => [
                'total_sales' => $totalSales,
                'top_selling_products' => $topSellingProducts,
                'order_statuses' => $orderStatuses,
                'daily_sales' => $dailySales,
                'weekly_sales' => $weeklySales,
                'monthly_sales' => $monthlySales,
                'annual_sales' => $annualSales,
                'top_payment_methods' => $topPaymentMethods,
            ],
        ]);
    }

}
