<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class getDeliveredShipmentsController
{
    private $guzzleClient;

    public function __construct()
    {
        $this->guzzleClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    public function getDeliveredShipments($clientId)
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

        $perPage = 50;
        $page = 1;

        // Cambio principal: buscar órdenes con shipping.status = 'delivered'
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'shipping.status' => 'delivered', // Solo envíos entregados
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

        if (empty($orders)) {
            return response()->json([
                'status' => 'success',
                'message' => 'No se encontraron productos entregados.',
                'data' => [],
            ]);
        }

        $deliveredProducts = $this->processOrdersAsync($orders, $credentials->access_token);

        return response()->json([
            'status' => 'success',
            'message' => 'Productos entregados obtenidos con éxito.',
            'total_delivered' => count($deliveredProducts),
            'data' => $deliveredProducts,
        ]);
    }

    private function processOrdersAsync($orders, $accessToken)
    {
        $deliveredProducts = [];
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        // Crear todas las peticiones
        $requests = [];
        $requestMap = [];

        foreach ($orders as $orderIndex => $order) {
            $shippingId = $order['shipping']['id'] ?? null;

            if ($shippingId) {
                // Petición para información del envío
                $requests[] = new Request('GET', "https://api.mercadolibre.com/shipments/{$shippingId}", $headers);
                $requestMap[count($requests) - 1] = ['type' => 'shipment', 'order_index' => $orderIndex];

                // Petición para historial del envío
                $historyHeaders = array_merge($headers, ['x-format-new' => 'true']);
                $requests[] = new Request('GET', "https://api.mercadolibre.com/shipments/{$shippingId}/history", $historyHeaders);
                $requestMap[count($requests) - 1] = ['type' => 'history', 'order_index' => $orderIndex];

                // Peticiones para detalles de productos
                foreach ($order['order_items'] as $itemIndex => $item) {
                    $productId = $item['item']['id'];
                    $variationId = $item['item']['variation_id'] ?? null;

                    $requests[] = new Request('GET', "https://api.mercadolibre.com/items/{$productId}", $headers);
                    $requestMap[count($requests) - 1] = ['type' => 'product', 'order_index' => $orderIndex, 'item_index' => $itemIndex];

                    // Si hay variación, crear petición para obtener sus detalles
                    if ($variationId && $variationId !== 'N/A') {
                        $requests[] = new Request('GET', "https://api.mercadolibre.com/items/{$productId}/variations/{$variationId}", $headers);
                        $requestMap[count($requests) - 1] = ['type' => 'variation', 'order_index' => $orderIndex, 'item_index' => $itemIndex];
                    }
                }
            }
        }

        // Ejecutar todas las peticiones usando Pool
        $responses = [];
        $pool = new Pool($this->guzzleClient, $requests, [
            'concurrency' => 10, // Número de peticiones concurrentes
            'fulfilled' => function ($response, $index) use (&$responses) {
                $responses[$index] = [
                    'success' => true,
                    'data' => json_decode($response->getBody()->getContents(), true)
                ];
            },
            'rejected' => function (RequestException $reason, $index) use (&$responses) {
                $responses[$index] = [
                    'success' => false,
                    'error' => $reason->getMessage()
                ];
                Log::warning('Petición fallida', ['index' => $index, 'error' => $reason->getMessage()]);
            },
        ]);

        try {
            // Ejecutar el pool de peticiones
            $promise = $pool->promise();
            $promise->wait();

            // Organizar respuestas por tipo
            $shipmentData = [];
            $historyData = [];
            $productData = [];
            $variationData = [];

            foreach ($responses as $index => $response) {
                if (!$response['success']) {
                    continue;
                }

                $mapInfo = $requestMap[$index];
                $key = $mapInfo['order_index'];

                switch ($mapInfo['type']) {
                    case 'shipment':
                        $shipmentData[$key] = $response['data'];
                        break;
                    case 'history':
                        $historyData[$key] = $response['data'];
                        break;
                    case 'product':
                        $productKey = "{$key}_{$mapInfo['item_index']}";
                        $productData[$productKey] = $response['data'];
                        break;
                    case 'variation':
                        $variationKey = "{$key}_{$mapInfo['item_index']}";
                        $variationData[$variationKey] = $response['data'];
                        break;
                }
            }

            // Procesar resultados
            foreach ($orders as $orderIndex => $order) {
                $shippingId = $order['shipping']['id'] ?? null;

                if (!$shippingId) {
                    continue;
                }

                // Obtener información del envío
                $shipmentInfo = $shipmentData[$orderIndex] ?? [];
                $shippingStatus = $shipmentInfo['status'] ?? null;

                // Verificar que el estado sea 'delivered'
                if ($shippingStatus !== 'delivered') {
                    Log::info('Pedido descartado - no está entregado', [
                        'order_id' => $order['id'],
                        'shipping_status' => $shippingStatus
                    ]);
                    continue;
                }

                // Obtener fecha de entrega del historial
                $deliveryDate = null;
                $shipmentHistory = $historyData[$orderIndex] ?? [];
                $processedHistory = [];

                if (!empty($shipmentHistory)) {
                    // El historial viene en diferentes formatos, intentemos ambos
                    $trackingEvents = [];

                    // Formato 1: shipmentHistory['tracking']
                    if (isset($shipmentHistory['tracking']) && is_array($shipmentHistory['tracking'])) {
                        $trackingEvents = $shipmentHistory['tracking'];
                    }
                    // Formato 2: shipmentHistory es directamente un array de eventos
                    elseif (is_array($shipmentHistory) && isset($shipmentHistory[0]['date'])) {
                        $trackingEvents = $shipmentHistory;
                    }

                    // Procesar eventos de tracking
                    foreach ($trackingEvents as $event) {
                        if (isset($event['status']) && $event['status'] === 'delivered' && !$deliveryDate) {
                            $deliveryDate = $event['date'] ?? null;
                        }

                        // Agregar evento procesado al historial
                        $processedHistory[] = [
                            'date' => $event['date'] ?? null,
                            'substatus' => $event['substatus'] ?? null,
                            'status' => $this->translateShippingStatus($event['status'] ?? '')
                        ];
                    }
                }

                // Obtener status actual y fecha actual (fuera del historial)
                $currentStatus = $this->translateShippingStatus($shippingStatus);
                $currentDate = Carbon::now()->toISOString();

                // Procesar items de la orden
                foreach ($order['order_items'] as $itemIndex => $item) {
                    $productId = $item['item']['id'];
                    $variationId = $item['item']['variation_id'] ?? 'N/A';
                    $size = 'N/A';
                    $sku = null;
                    $skuSource = 'not_found';

                    $productKey = "{$orderIndex}_{$itemIndex}";

                    // Obtener detalles del producto
                    if (isset($productData[$productKey])) {
                        $sku = $this->extractSku($item, $productData[$productKey], $skuSource);
                    }

                    // Obtener información de variación si existe
                    if ($variationId !== 'N/A' && isset($variationData[$productKey])) {
                        foreach ($variationData[$productKey]['attribute_combinations'] ?? [] as $attribute) {
                            if (in_array(strtolower($attribute['id']), ['size', 'talle'])) {
                                $size = $attribute['value_name'];
                                break;
                            }
                        }
                    }

                    $deliveredProducts[] = [
                        'id' => $productId,
                        'order_id' => $order['shipping']['id'],
                        'variation_id' => $variationId,
                        'title' => $item['item']['title'],
                        'quantity' => $item['quantity'],
                        'size' => $size,
                        'sku' => $sku ?: 'No se encuentra disponible en mercado libre',
                        'sku_source' => $skuSource,
                        'sku_missing_reason' => $skuSource === 'not_found' ?
                            'No se encontraron campos seller_custom_field, seller_sku ni atributos SKU en el producto' : null,
                        'delivery_date' => $deliveryDate,
                        'current_status' => $currentStatus,
                        'current_date' => $currentDate,
                        'shipment_history' => $processedHistory,
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Error procesando peticiones asíncronas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [];
        }

        return $deliveredProducts;
    }

    private function translateShippingStatus($status)
    {
        if (empty($status)) {
            return '';
        }

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

        return $translations[strtolower($status)] ?? $status;
    }

    private function extractSku($item, $productData, &$skuSource)
    {
        // 1. Primero buscar en seller_custom_field del ítem del pedido
        $sku = $item['item']['seller_custom_field'] ?? null;
        if (!empty($sku)) {
            $skuSource = 'seller_custom_field';
            return $sku;
        }

        // 2. Si no está, buscar en seller_sku del producto
        if (isset($productData['seller_sku']) && !empty($productData['seller_sku'])) {
            $skuSource = 'seller_sku';
            return $productData['seller_sku'];
        }

        // 3. Si aún no se encontró, buscar en los atributos del producto
        if (isset($productData['attributes'])) {
            foreach ($productData['attributes'] as $attribute) {
                if (in_array(strtolower($attribute['id']), ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) ||
                    in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia', 'codigo', 'código de producto'])) {
                    $skuSource = 'attributes';
                    return $attribute['value_name'];
                }
            }
        }

        // 4. Si sigue sin encontrarse, intentar con el modelo como último recurso
        if (isset($productData['attributes'])) {
            foreach ($productData['attributes'] as $attribute) {
                if (strtolower($attribute['id']) === 'model' ||
                    strtolower($attribute['name']) === 'modelo') {
                    $skuSource = 'model_fallback';
                    return $attribute['value_name'];
                }
            }
        }

        // 5. No se encontró SKU
        $skuSource = 'not_found';
        return null;
    }
}
