<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class getDeliveredShipmentsController
{
    private $guzzleClient;
    private $credentials;
    private $accessToken;

    public function __construct()
    {
        $this->guzzleClient = new Client([
            'base_uri' => 'https://api.mercadolibre.com/',
            'timeout' => 30,
        ]);
    }

    public function getDeliveredShipments($clientId)
    {
        $this->credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$this->credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        $this->refreshTokenIfNeeded();

        try {
            $userId = $this->getUserId();
            $deliveredShipments = $this->processOrders($userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Envíos entregados obtenidos con éxito.',
                'data' => $deliveredShipments,
                'totalItems' => count($deliveredShipments),
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getDeliveredShipments: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar los envíos.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        $this->accessToken = $this->credentials->access_token;
    }

    private function getUserId()
    {
        $response = Http::withToken($this->accessToken)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            throw new \Exception('No se pudo obtener el ID del usuario. Por favor, valide su token.');
        }

        return $response->json()['id'];
    }

    private function processOrders($userId)
    {
        $perPage = 50;
        $page = 1;
        $deliveredShipments = [];

        do {
            $orders = $this->fetchOrdersPage($userId, $page, $perPage);
            $deliveredShipments = array_merge($deliveredShipments, $this->processOrdersBatch($orders));
            $page++;
        } while (count($orders) === $perPage && $page <= 10);

        return $deliveredShipments;
    }

    private function fetchOrdersPage($userId, $page, $perPage)
    {
        $response = Http::withToken($this->accessToken)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'offset' => ($page - 1) * $perPage,
                'limit' => $perPage
            ]);

        if ($response->failed()) {
            throw new \Exception('Error al conectar con la API de MercadoLibre para obtener órdenes.');
        }

        return $response->json()['results'];
    }

    private function processOrdersBatch($orders)
    {
        $promises = [];
        $orderContexts = [];

        // Prepare all async requests
        foreach ($orders as $order) {
            $shippingId = $order['shipping']['id'] ?? null;
            if (!$shippingId) continue;

            $orderContexts[$shippingId] = $order;

            // Shipment info request
            $promises["shipment_{$shippingId}"] = $this->guzzleClient->getAsync("shipments/{$shippingId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}"
                ]
            ]);

            // Shipment history request
            $promises["history_{$shippingId}"] = $this->guzzleClient->getAsync("shipments/{$shippingId}/history", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'x-format-new' => 'true'
                ]
            ]);
        }

        // Wait for all requests to complete
        $responses = Promise\Utils::settle($promises)->wait();

        // Process responses
        $deliveredShipments = [];
        foreach ($orderContexts as $shippingId => $order) {
            try {
                $shipmentInfo = $this->processShipmentResponse($responses["shipment_{$shippingId}"]);
                if ($shipmentInfo['status'] !== 'delivered') continue;

                $shipmentHistory = $this->processHistoryResponse($responses["history_{$shippingId}"]);

                $deliveredShipments = array_merge(
                    $deliveredShipments,
                    $this->processOrderItems($order, $shippingId, $shipmentInfo, $shipmentHistory)
                );

            } catch (\Exception $e) {
                Log::error("Error procesando envío {$shippingId}: " . $e->getMessage());
                continue;
            }
        }

        return $deliveredShipments;
    }

    private function processShipmentResponse($response)
    {
        if ($response['state'] !== 'fulfilled') {
            throw new \Exception('Error al obtener información del envío');
        }

        $data = json_decode($response['value']->getBody(), true);
        if (!is_array($data)) {
            throw new \Exception('Formato de respuesta inválido para información de envío');
        }

        return $data;
    }

    private function processHistoryResponse($response)
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

        if ($response['state'] !== 'fulfilled') {
            return ['status' => 'unknown', 'date_created' => null];
        }

        $data = json_decode($response['value']->getBody(), true);
        if (!is_array($data)) {
            return ['status' => 'unknown', 'date_created' => null];
        }

        $status = $data['status'] ?? 'unknown';
        return [
            'status' => $translations[$status] ?? $status,
            'date_created' => $data['date_created'] ?? null
        ];
    }

    private function processOrderItems($order, $shippingId, $shipmentInfo, $shipmentHistory)
    {
        $clientName = $this->getClientName($order);
        $address = $this->getAddress($shipmentInfo);
        $receiverName = $this->getReceiverName($shipmentInfo);
        $dateDelivered = $this->getDeliveryDate($shipmentInfo);

        $itemPromises = [];
        foreach ($order['order_items'] as $item) {
            $productId = $item['item']['id'];
            $itemPromises[$productId] = $this->guzzleClient->getAsync("items/{$productId}", [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"]
            ]);
        }

        $itemResponses = Promise\Utils::settle($itemPromises)->wait();

        $processedItems = [];
        foreach ($order['order_items'] as $item) {
            $productId = $item['item']['id'];
            $variationId = $item['item']['variation_id'] ?? 'N/A';

            try {
                $productData = $this->processProductResponse($itemResponses[$productId]);
                $sku = $this->extractSku($item, $productData);
                $size = $this->getSize($productId, $variationId);

                $processedItems[] = [
                    'id' => $shippingId,
                    'order_id' => $order['id'],
                    'title' => $item['item']['title'],
                    'quantity' => $item['quantity'],
                    'size' => $size,
                    'sku' => $sku,
                    'shipment_history' => $shipmentHistory,
                    'clientName' => $clientName,
                    'address' => $address,
                    'receiver_name' => $receiverName,
                    'date_delivered' => $dateDelivered,
                ];

            } catch (\Exception $e) {
                Log::error("Error procesando item {$productId}: " . $e->getMessage());
                continue;
            }
        }

        return $processedItems;
    }

    private function processProductResponse($response)
    {
        if ($response['state'] !== 'fulfilled') {
            throw new \Exception('Error al obtener información del producto');
        }

        $data = json_decode($response['value']->getBody(), true);
        if (!is_array($data)) {
            throw new \Exception('Formato de respuesta inválido para información de producto');
        }

        return $data;
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

    private function getSize($productId, $variationId)
    {
        if ($variationId === 'N/A') return 'N/A';

        try {
            $response = $this->guzzleClient->get("items/{$productId}/variations/{$variationId}", [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"]
            ]);

            $variationData = json_decode($response->getBody(), true);
            foreach ($variationData['attribute_combinations'] ?? [] as $attribute) {
                if (in_array(strtolower($attribute['id']), ['size', 'talle'])) {
                    return $attribute['value_name'];
                }
            }
        } catch (\Exception $e) {
            Log::error("Error obteniendo tamaño para variación {$variationId}: " . $e->getMessage());
        }

        return 'N/A';
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
