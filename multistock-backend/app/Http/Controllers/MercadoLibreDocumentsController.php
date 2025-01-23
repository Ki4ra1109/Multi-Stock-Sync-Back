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
                // Add other order details as needed
            ];
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
                // Add other order details as needed
            ];
        }

        // Return sales by month data
        return response()->json([
            'status' => 'success',
            'message' => 'Ventas anuales obtenidas con éxito.',
            'data' => $salesByMonth,
        ]);
    }
}
