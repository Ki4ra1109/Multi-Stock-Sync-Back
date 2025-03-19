<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getProductsToDispatchController
{
    public function getProductsToDispatch($clientId)
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
        $month = request()->query('month');

        $page = request()->query('page', 1);
        $perPage = request()->query('per_page', 10);

        if ($month) {
            $dateFrom = "{$year}-{$month}-01T00:00:00.000-00:00";
            $dateTo = date("Y-m-t\T23:59:59.999-00:00", strtotime($dateFrom));
        } else {
            $dateFrom = "{$year}-01-01T00:00:00.000-00:00";
            $dateTo = "{$year}-12-31T23:59:59.999-00:00";
        }

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'order.date_created.from' => $dateFrom,
                'order.date_created.to' => $dateTo,
                'offset' => ($page - 1) * $perPage,
                'limit' => $perPage
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $productsToDispatch = [];

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $variationId = $item['item']['variation_id'] ?? 'N/A';
                $size = 'N/A';

                
                $productDetailsResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/items/{$productId}");

                if ($productDetailsResponse->successful()) {
                    $productData = $productDetailsResponse->json();

                    
                    if ($variationId !== 'N/A') {
                        $variationResponse = Http::withToken($credentials->access_token)
                            ->get("https://api.mercadolibre.com/items/{$productId}/variations/{$variationId}");

                        if ($variationResponse->successful()) {
                            $variationData = $variationResponse->json();

                            foreach ($variationData['attribute_combinations'] ?? [] as $attribute) {
                                if (in_array(strtolower($attribute['id']), ['size', 'talle'])) {
                                    $size = $attribute['value_name'];
                                    break;
                                }
                            }
                        }
                    }

                    $shipmentId = $order['shipping']['id'];
                    $shipmentHistoryResponse = Http::withToken($credentials->access_token)
                        ->get("https://api.mercadolibre.com/shipments/{$shipmentId}/history", [
                            'headers' => [
                                'x-format-new' => 'true'
                            ]
                        ]);

                    $shipmentHistory = [];
                    if ($shipmentHistoryResponse->successful()) {
                        $shipmentHistory = $shipmentHistoryResponse->json();

                        // Traducir estado del envío si existe
                        if (isset($shipmentHistory['status'])) {
                            $translations = [
                                'pending' => 'pendiente',
                                'shipped' => 'enviado',
                                'delivered' => 'entregado',
                                'not_delivered' => 'no entregado',
                                'returned' => 'devuelto',
                                'cancelled' => 'cancelado',
                                'ready_to_ship' => 'listo para enviar',
                                'handling' => 'en preparación',
                            ];

                            $shipmentHistory['status'] = $translations[$shipmentHistory['status']] ?? $shipmentHistory['status'];
                        }
                    }

                    $productsToDispatch[] = [
                        'id' => $productId,
                        'variation_id' => $variationId,
                        'title' => $item['item']['title'],
                        'quantity' => $item['quantity'],
                        'size' => $size,
                        'sku' => $item['item']['seller_custom_field'] ?? 'N/A',
                        'shipment_history' => $shipmentHistory,
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Productos listos para despachar obtenidos con éxito.',
            'data' => $productsToDispatch,
        ]);
    }
}
