<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
{

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

        // Get query parameter for year
        $year = request()->query('year', date('Y')); // Default to current year

        // Calculate date range based on the provided year or all times
        if ($year === 'alloftimes') {
            $dateFrom = '2000-01-01T00:00:00.000-00:00'; // Arbitrary start date for all times
            $dateTo = date('Y-m-d\T23:59:59.999-00:00'); // Current date and time
        } else {
            $dateFrom = "{$year}-01-01T00:00:00.000-00:00";
            $dateTo = "{$year}-12-31T23:59:59.999-00:00";
        }

        // API request to get all sales within the specified date range
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
            'request_date' => date('Y-m-d H:i:s'), // Include the request date
            'data' => $paymentMethods,
        ]);
    }
    
}