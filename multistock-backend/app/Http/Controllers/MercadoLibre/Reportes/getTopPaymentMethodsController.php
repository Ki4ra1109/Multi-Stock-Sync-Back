<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getTopPaymentMethodsController
{
    /**
     * Get top payment methods from MercadoLibre API using client_id.
     */
    public function getTopPaymentMethods($clientId)
{

    $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

    if (!$credentials) {
        return response()->json([
            'status' => 'error',
            'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
        ], 404);
    }

    if ($credentials->isTokenExpired()) {
        return response()->json([
            'status' => 'error',
            'message' => 'El token ha expirado. Por favor, renueve su token.',
        ], 401);
    }


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


    $year = request()->query('year', date('Y')); 
    $paymentStatus = request()->query('payment_status', 'all');

    
    if ($year === 'alloftimes') {
        $dateFrom = '2000-01-01T00:00:00.000-00:00';
        $dateTo = date('Y-m-d\T23:59:59.999-00:00');
    } else {
        $dateFrom = "{$year}-01-01T00:00:00.000-00:00";
        $dateTo = "{$year}-12-31T23:59:59.999-00:00";
    }


    $url = "https://api.mercadolibre.com/orders/search?seller={$userId}&order.date_created.from={$dateFrom}&order.date_created.to={$dateTo}";

    if ($paymentStatus !== 'all') {
        $url .= "&payments.status={$paymentStatus}";
    }

    $response = Http::withToken($credentials->access_token)->get($url);

    if ($response->failed()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error al conectar con la API de MercadoLibre.',
            'error' => $response->json(),
        ], $response->status());
    }


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


    unset($paymentMethods['paypal']);

    arsort($paymentMethods);

    return response()->json([
        'status' => 'success',
        'message' => 'Métodos de pago más utilizados obtenidos con éxito.',
        'request_date' => date('Y-m-d H:i:s'),
        'data' => $paymentMethods,
    ]);
}
}