<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getRefundsByCategoryController
{
    public function getRefundsByCategory($clientId)
    {
        // Get credentials by client_id
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        // Check if credentials exist
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No valid credentials found for the provided client_id.',
            ], 404);
        }

        // Check if token is expired
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token has expired. Please renew your token.',
            ], 401);
        }

        // Get user id from token
        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not get user ID. Please validate your token.',
                'error' => $response->json(),
            ], 500);
        }

        $userId = $response->json()['id'];

        // Get query parameters for date range and category
        $dateFrom = request()->query('date_from', date('Y-m-01')); // Default to first day of current month
        $dateTo = request()->query('date_to', date('Y-m-t')); // Default to last day of current month
        $category = request()->query('category', ''); // Default to empty (no category filter)

        // API request to get refunds or returns by category
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'cancelled',
                'order.date_created.from' => "{$dateFrom}T00:00:00.000-00:00",
                'order.date_created.to' => "{$dateTo}T23:59:59.999-00:00",
                'category' => $category,
            ]);

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error connecting to MercadoLibre API.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Process refunds data
        $orders = $response->json()['results'];
        $refundsByCategory = [];

        foreach ($orders as $order) {
            // Get shipping details for each order
            $shippingResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/shipments/{$order['shipping']['id']}");
            
            $shippingDetails = $shippingResponse->successful() ? $shippingResponse->json() : null;

            // Get buyer details
            $buyerResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/users/{$order['buyer']['id']}");
            
            $buyerDetails = $buyerResponse->successful() ? $buyerResponse->json() : null;

            // Get billing information for the order
            $billingInfoResponse = Http::withToken($credentials->access_token)
                ->withHeaders(['x-version' => '2'])
                ->get("https://api.mercadolibre.com/orders/{$order['id']}/billing_info");

            $billingInfo = $billingInfoResponse->successful() ? $billingInfoResponse->json() : null;

            foreach ($order['order_items'] as $item) {
                $categoryId = $item['item']['category_id'];
                if (!isset($refundsByCategory[$categoryId])) {
                    $refundsByCategory[$categoryId] = [
                        'category_id' => $categoryId,
                        'total_refunds' => 0,
                        'orders' => []
                    ];
                }
                $refundsByCategory[$categoryId]['total_refunds'] += $order['total_amount'];

                // Prepare shipping address information
                $shippingAddress = $shippingDetails ? [
                    'address' => $shippingDetails['receiver_address']['street_name'] ?? '',
                    'number' => $shippingDetails['receiver_address']['street_number'] ?? '',
                    'city' => $shippingDetails['receiver_address']['city']['name'] ?? '',
                    'state' => $shippingDetails['receiver_address']['state']['name'] ?? '',
                    'country' => $shippingDetails['receiver_address']['country']['name'] ?? '',
                    'comments' => $shippingDetails['receiver_address']['comment'] ?? '',
                ] : null;

                $refundsByCategory[$categoryId]['orders'][] = [
                    'id' => $order['id'],
                    'created_date' => $order['date_created'],
                    'total_amount' => $order['total_amount'],
                    'status' => $order['status'],
                    'product' => [
                        'title' => $item['item']['title'],
                        'quantity' => $item['quantity'],
                        'price' => $item['unit_price'],
                    ]
                ];
            }
        }

        // Return refunds by category data
        return response()->json([
            'status' => 'success',
            'message' => 'Refunds by category retrieved successfully.',
            'data' => $refundsByCategory,
        ]);
    }
}