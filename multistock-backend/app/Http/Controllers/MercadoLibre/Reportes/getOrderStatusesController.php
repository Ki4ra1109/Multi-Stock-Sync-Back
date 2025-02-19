<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;

class getOrderStatusesController
{

    /**
     * Get order statuses (paid, pending, canceled) from MercadoLibre API using client_id.
    */
    public function getOrderStatuses(Request $request, $clientId)
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

        // Get month and year from query parameters or use current month and year
        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);

        // API request to get order statuses
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.date_created.from={$year}-{$month}-01T00:00:00.000-00:00&order.date_created.to={$year}-{$month}-".Carbon::now()->daysInMonth."T23:59:59.999-00:00");

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Process order statuses and related products
        $orders = $response->json()['results'];
        $statuses = [
            'paid' => 0,
            'pending' => 0,
            'cancelled' => 0,
        ];
        $products = [];

        foreach ($orders as $order) {
            if (isset($statuses[$order['status']])) {
                $statuses[$order['status']]++;
            }
            foreach ($order['order_items'] as $item) {
                $item['item']['status'] = $order['status']; // Add product status
                $products[] = $item['item'];
            }
        }

        // Return order statuses and related products data
        return response()->json([
            'status' => 'success',
            'message' => 'Estados de órdenes y productos relacionados obtenidos con éxito.',
            'data' => [
                'statuses' => $statuses,
                'products' => $products,
            ],
        ]);
    }
    
}