<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials->client_id,
                'client_secret' => $credentials->client_secret,
                'refresh_token' => $credentials->refresh_token,
            ]);

            if ($refreshResponse->failed()) {
                return response()->json(['error' => 'No se pudo refrescar el token'], 401);
            }

            $data = $refreshResponse->json();
            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
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

       
        $dateFrom = Carbon::now()->format('Y-m-d\T00:00:00.000-00:00');
        $dateTo = Carbon::now()->format('Y-m-d\T23:59:59.999-00:00');

        
        $perPage = 50;
        $page = 1;

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
            $shippingId = $order['shipping']['id'] ?? null;
            Log::info('Procesando orden', ['order_id' => $order['id'], 'shipping_id' => $shippingId]);

            if ($shippingId) {
                $leadTimeResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/shipments/{$shippingId}/lead_time");
                $shipmentInfoResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/shipments/{$shippingId}");

                Log::info('Respuesta lead_time', [
                    'shipping_id' => $shippingId,
                    'success' => $leadTimeResponse->successful(),
                    'body' => $leadTimeResponse->body()
                ]);
                Log::info('Respuesta shipment_info', [
                    'shipping_id' => $shippingId,
                    'success' => $shipmentInfoResponse->successful(),
                    'body' => $shipmentInfoResponse->body()
                ]);

                $shipmentInfo = $shipmentInfoResponse->successful() ? $shipmentInfoResponse->json() : [];
                $shippingStatus = $shipmentInfo['status'] ?? null;

                
                if (!in_array($shippingStatus, ['ready_to_ship', 'handling', 'shipped'])) {
                    Log::info('Pedido descartado por estado de envío', [
                        'order_id' => $order['id'],
                        'shipping_status' => $shippingStatus
                    ]);
                    continue;
                }
            }

            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $variationId = $item['item']['variation_id'] ?? 'N/A';
                $size = 'N/A';

                $productDetailsResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/items/{$productId}");

                if ($productDetailsResponse->successful()) {
                    $productData = $productDetailsResponse->json();

                    // 1. Primero buscar en seller_custom_field del ítem del pedido
                    $sku = $item['item']['seller_custom_field'] ?? null;
                    $skuSource = 'not_found';

                    // 2. Si no está, buscar en seller_sku del producto
                    if (empty($sku)) {
                        if (isset($productData['seller_sku'])) {
                            $sku = $productData['seller_sku'];
                            $skuSource = 'seller_sku';
                        }
                    } else {
                        $skuSource = 'seller_custom_field';
                    }

                    // 3. Si aún no se encontró, buscar en los atributos del producto
                    if (empty($sku) && isset($productData['attributes'])) {
                        foreach ($productData['attributes'] as $attribute) {
                            if (in_array(strtolower($attribute['id']), ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) ||
                                in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia', 'codigo', 'código de producto'])) {
                                $sku = $attribute['value_name'];
                                $skuSource = 'attributes';
                                break;
                            }
                        }
                    }

                    // 4. Si sigue sin encontrarse, intentar con el modelo como último recurso
                    if (empty($sku) && isset($productData['attributes'])) {
                        foreach ($productData['attributes'] as $attribute) {
                            if (strtolower($attribute['id']) === 'model' ||
                                strtolower($attribute['name']) === 'modelo') {
                                $sku = $attribute['value_name'];
                                $skuSource = 'model_fallback';
                                break;
                            }
                        }
                    }

                    // 5. Establecer mensaje predeterminado si no se encontró SKU
                    if (empty($sku)) {
                        $sku = 'No se encuentra disponible en mercado libre';
                    }

                    // Manejo de variaciones (tamaño)
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

                    // Historial de envío
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

                        // Traducir estado del envío
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
                        'order_id' => $order['shipping']['id'],
                        'variation_id' => $variationId,
                        'title' => $item['item']['title'],
                        'quantity' => $item['quantity'],
                        'size' => $size,
                        'sku' => $sku,
                        'sku_source' => $skuSource,
                        'sku_missing_reason' => $skuSource === 'not_found' ?
                            'No se encontraron campos seller_custom_field, seller_sku ni atributos SKU en el producto' : null,
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