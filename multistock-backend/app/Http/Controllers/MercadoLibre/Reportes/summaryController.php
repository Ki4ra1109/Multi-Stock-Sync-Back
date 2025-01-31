<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

use App\Http\Controllers\MercadoLibre\Reportes\getTopSellingProductsController;
use App\Http\Controllers\MercadoLibre\Reportes\getOrderStatusesController;
use App\Http\Controllers\MercadoLibre\Reportes\getDailySalesController;
use App\Http\Controllers\MercadoLibre\Reportes\getSalesByWeekController;
use App\Http\Controllers\MercadoLibre\Reportes\getSalesByMonthController;
use App\Http\Controllers\MercadoLibre\Reportes\getAnnualSalesController;
use App\Http\Controllers\MercadoLibre\Reportes\getTopPaymentMethodsController;

class summaryController
{

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
        $topSellingProductsController = new getTopSellingProductsController();
        $topSellingProductsResponse = $topSellingProductsController->getTopSellingProducts($clientId);
        if ($topSellingProductsResponse->getStatusCode() !== 200) {
            return $topSellingProductsResponse;
        }
        $topSellingProducts = array_slice($topSellingProductsResponse->getData(true)['data'], 0, 5);

        // Get order statuses
        $orderStatusesController = new getOrderStatusesController();
        $orderStatusesResponse = $orderStatusesController->getOrderStatuses(new Request(), $clientId);
        if ($orderStatusesResponse->getStatusCode() !== 200) {
            return $orderStatusesResponse;
        }
        $orderStatuses = $orderStatusesResponse->getData(true)['data'];

        // Get daily sales (summary only)
        $dailySalesController = new getDailySalesController();
        $dailySalesResponse = $dailySalesController->getDailySales($clientId);
        if ($dailySalesResponse->getStatusCode() !== 200) {
            return $dailySalesResponse;
        }
        $dailySales = $dailySalesResponse->getData(true)['data']['total_sales'];

        // Get weekly sales (summary only)
        $currentWeekStart = \Carbon\Carbon::now()->startOfWeek()->toDateString();
        $currentWeekEnd = \Carbon\Carbon::now()->endOfWeek()->toDateString();
        $weeklySalesController = new getSalesByWeekController();
        $weeklySalesResponse = $weeklySalesController->getSalesByWeek(new Request([
            'week_start_date' => $currentWeekStart,
            'week_end_date' => $currentWeekEnd
        ]), $clientId);
        if ($weeklySalesResponse->getStatusCode() !== 200) {
            return $weeklySalesResponse;
        }
        $weeklySales = $weeklySalesResponse->getData(true)['data']['total_sales'];

        // Get monthly sales (summary only)
        $monthlySalesController = new getSalesByMonthController();
        $monthlySalesResponse = $monthlySalesController->getSalesByMonth($clientId);
        if ($monthlySalesResponse->getStatusCode() !== 200) {
            return $monthlySalesResponse;
        }
        $monthlySales = array_sum(array_column($monthlySalesResponse->getData(true)['data'], 'total_amount'));

        // Get annual sales (summary only)
        $annualSalesController = new getAnnualSalesController();
        $annualSalesResponse = $annualSalesController->getAnnualSales($clientId);
        if ($annualSalesResponse->getStatusCode() !== 200) {
            return $annualSalesResponse;
        }
        $annualSales = array_sum(array_column($annualSalesResponse->getData(true)['data'], 'total_amount'));

        // Get top payment methods (limit to 3)
        $topPaymentMethodsController = new getTopPaymentMethodsController();
        $topPaymentMethodsResponse = $topPaymentMethodsController->getTopPaymentMethods($clientId);
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