<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetDeliveredShipmentsController
{
    private $guzzleClient;
    private $credentials;
    private $accessToken;
    private $maxPages = 5; // Reduced from 10 to prevent timeouts
    private $perPage = 30; // Reduced from 50
    private $itemBatchSize = 5; // Process items in smaller batches

    public function __construct()
    {
        $this->guzzleClient = new Client([
            'base_uri' => 'https://api.mercadolibre.com/',
            'timeout' => 15,
            'connect_timeout' => 5,
        ]);
    }

    public function getDeliveredShipments($clientId)
    {
        set_time_limit(60); // Increase timeout to 60 seconds

        try {
            $this->initializeCredentials($clientId);
            $userId = $this->getUserId();

            $result = $this->processAllOrders($userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Envíos entregados obtenidos con éxito.',
                'data' => $result['shipments'],
                'totalItems' => $result['count'],
                'partial' => $result['partial'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getDeliveredShipments: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar los envíos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function initializeCredentials($clientId)
    {
        $this->credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
        if (!$this->credentials) {
            throw new \Exception('No se encontraron credenciales válidas para el client_id proporcionado.');
        }

        $this->refreshTokenIfNeeded();
        $this->accessToken = $this->credentials->access_token;
    }

    private function refreshTokenIfNeeded()
    {
        if ($this->credentials->isTokenExpired()) {
            $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->credentials->client_id,
                'client_secret' => $this->credentials->client_secret,
                'refresh_token' => $this->credentials->refresh_token,
            ]);

            if ($response->failed()) {
                throw new \Exception('No se pudo refrescar el token');
            }

            $data = $response->json();
            $this->credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }
    }

    private function getUserId()
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(10)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            throw new \Exception('No se pudo obtener el ID del usuario');
        }

        return $response->json()['id'];
    }

    private function processAllOrders($userId)
    {
        $page = 1;
        $allShipments = [];
        $partial = false;

        do {
            $orders = $this->fetchOrdersPage($userId, $page);
            $batchResult = $this->processOrderBatch($orders);

            $allShipments = array_merge($allShipments, $batchResult['shipments']);
            $partial = $batchResult['partial'];

            $page++;

            if ($partial) {
                Log::warning('Partial results due to processing limits');
                break;
            }

        } while (count($orders) === $this->perPage && $page <= $this->maxPages);

        return [
            'shipments' => $allShipments,
            'count' => count($allShipments),
            'partial' => $partial,
        ];
    }

    private function fetchOrdersPage($userId, $page)
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'offset' => ($page - 1) * $this->perPage,
                'limit' => $this->perPage
            ]);

        if ($response->failed()) {
            throw new \Exception('Error al obtener órdenes');
        }

        return $response->json()['results'];
    }

    private function processOrderBatch($orders)
    {
        $shipmentPromises = [];
        $orderContexts = [];

        foreach ($orders as $order) {
            $shippingId = $order['shipping']['id'] ?? null;
            if (!$shippingId) continue;

            $orderContexts[$shippingId] = $order;

            $shipmentPromises["shipment_{$shippingId}"] = $this->guzzleClient->getAsync("shipments/{$shippingId}", [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"]
            ]);
        }

        $responses = Promise\Utils::settle($shipmentPromises)->wait();

        $processedShipments = [];
        $partial = false;

        foreach ($responses as $key => $response) {
            $shippingId = str_replace('shipment_', '', $key);
            $order = $orderContexts[$shippingId] ?? null;

            if (!$order || $response['state'] !== 'fulfilled') continue;

            $shipmentInfo = json_decode($response['value']->getBody(), true);
            if (!is_array($shipmentInfo)) continue;

            if (($shipmentInfo['status'] ?? null) !== 'delivered') continue;

            $shipmentResult = $this->processDeliveredShipment($order, $shippingId, $shipmentInfo);
            $processedShipments = array_merge($processedShipments, $shipmentResult['shipments']);
            $partial = $partial || $shipmentResult['partial'];

            if ($partial) break;
        }

        return [
            'shipments' => $processedShipments,
            'partial' => $partial,
        ];
    }

    private function processDeliveredShipment($order, $shippingId, $shipmentInfo)
    {
        $commonData = [
            'clientName' => $this->getClientName($order),
            'address' => $this->getAddress($shipmentInfo),
            'receiverName' => $this->getReceiverName($shipmentInfo),
            'dateDelivered' => $this->getDeliveryDate($shipmentInfo),
            'shipmentHistory' => $this->getShipmentHistory($shippingId),
        ];

        $itemBatches = array_chunk($order['order_items'], $this->itemBatchSize);
        $processedItems = [];
        $partial = false;

        foreach ($itemBatches as $batch) {
            $batchResult = $this->processItemBatch($batch, $shippingId, $order, $commonData);
            $processedItems = array_merge($processedItems, $batchResult['items']);
            $partial = $partial || $batchResult['partial'];

            if ($partial) break;
        }

        return [
            'shipments' => $processedItems,
            'partial' => $partial,
        ];
    }

    private function processItemBatch($items, $shippingId, $order, $commonData)
    {
        $itemPromises = [];
        $itemContexts = [];

        foreach ($items as $item) {
            $productId = $item['item']['id'];
            $itemContexts[$productId] = [
                'item' => $item,
                'variationId' => $item['item']['variation_id'] ?? 'N/A'
            ];

            $itemPromises[$productId] = $this->guzzleClient->getAsync("items/{$productId}", [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
                'timeout' => 10
            ]);
        }

        $responses = Promise\Utils::settle($itemPromises)->wait();

        $processedItems = [];
        $partial = false;

        foreach ($responses as $productId => $response) {
            $context = $itemContexts[$productId] ?? null;
            if (!$context || $response['state'] !== 'fulfilled') continue;

            $productData = json_decode($response['value']->getBody(), true);
            if (!is_array($productData)) continue;

            $processedItems[] = $this->buildItemResult(
                $context['item'],
                $shippingId,
                $order,
                $commonData,
                $productData,
                $context['variationId']
            );

            // Check memory usage
            if (memory_get_usage(true) > 50 * 1024 * 1024) { // 50MB
                $partial = true;
                break;
            }
        }

        return [
            'items' => $processedItems,
            'partial' => $partial,
        ];
    }

    private function buildItemResult($item, $shippingId, $order, $commonData, $productData, $variationId)
    {
        return [
            'id' => $shippingId,
            'order_id' => $order['id'],
            'title' => $item['item']['title'],
            'quantity' => $item['quantity'],
            'size' => $this->getItemSize($productData['id'], $variationId),
            'sku' => $this->extractSku($item, $productData),
            'shipment_history' => $commonData['shipmentHistory'],
            'clientName' => $commonData['clientName'],
            'address' => $commonData['address'],
            'receiver_name' => $commonData['receiverName'],
            'date_delivered' => $commonData['dateDelivered'],
        ];
    }

    private function getShipmentHistory($shippingId)
    {
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

        try {
            $response = $this->guzzleClient->get("shipments/{$shippingId}/history", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'x-format-new' => 'true'
                ],
                'timeout' => 10
            ]);

            $data = json_decode($response->getBody(), true);
            $status = $data['status'] ?? 'unknown';

            return [
                'status' => $translations[$status] ?? $status,
                'date_created' => $data['date_created'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error("Error getting history for shipment {$shippingId}: " . $e->getMessage());
            return ['status' => 'unknown', 'date_created' => null];
        }
    }

    private function getItemSize($productId, $variationId)
    {
        if ($variationId === 'N/A') return 'N/A';

        try {
            $response = $this->guzzleClient->get("items/{$productId}/variations/{$variationId}", [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
                'timeout' => 5
            ]);

            $data = json_decode($response->getBody(), true);
            foreach ($data['attribute_combinations'] ?? [] as $attribute) {
                if (in_array(strtolower($attribute['id']), ['size', 'talle'])) {
                    return $attribute['value_name'];
                }
            }
        } catch (\Exception $e) {
            Log::error("Error getting size for variation {$variationId}: " . $e->getMessage());
        }

        return 'N/A';
    }

    private function extractSku($item, $productData)
    {
        $sku = $item['item']['seller_custom_field'] ?? null;

        if (empty($sku) && isset($productData['seller_sku'])) {
            $sku = $productData['seller_sku'];
        }

        if (empty($sku) && isset($productData['attributes'])) {
            foreach ($productData['attributes'] as $attribute) {
                if (in_array(strtolower($attribute['id']), ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) ||
                    in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia', 'codigo', 'código de producto'])) {
                    $sku = $attribute['value_name'];
                    break;
                }
            }
        }

        if (empty($sku) && isset($productData['attributes'])) {
            foreach ($productData['attributes'] as $attribute) {
                if (strtolower($attribute['id']) === 'model' ||
                    strtolower($attribute['name']) === 'modelo') {
                    $sku = $attribute['value_name'];
                    break;
                }
            }
        }

        return $sku ?: 'No se encuentra disponible en mercado libre';
    }

    private function getClientName($order)
    {
        if (isset($order['buyer']['nickname'])) {
            return $order['buyer']['nickname'];
        }

        if (isset($order['buyer']['first_name']) && isset($order['buyer']['last_name'])) {
            return trim($order['buyer']['first_name'] . ' ' . $order['buyer']['last_name']);
        }

        return null;
    }

    private function getAddress($shipmentInfo)
    {
        if (!isset($shipmentInfo['receiver_address']) || !is_array($shipmentInfo['receiver_address'])) {
            return null;
        }

        $receiverAddress = $shipmentInfo['receiver_address'];
        return implode(', ', array_filter([
            $receiverAddress['street_name'] ?? null,
            $receiverAddress['street_number'] ?? null,
            $receiverAddress['city']['name'] ?? null,
            $receiverAddress['state']['name'] ?? null,
            $receiverAddress['zip_code'] ?? null
        ]));
    }

    private function getReceiverName($shipmentInfo)
    {
        return $shipmentInfo['receiver_address']['receiver_name'] ?? null;
    }

    private function getDeliveryDate($shipmentInfo)
    {
        if (!isset($shipmentInfo['status_history']) || !is_array($shipmentInfo['status_history'])) {
            return null;
        }

        foreach ($shipmentInfo['status_history'] as $historyItem) {
            if (isset($historyItem['status']) && $historyItem['status'] === 'delivered') {
                return $historyItem['date_created'] ?? null;
            }
        }

        return null;
    }
}
