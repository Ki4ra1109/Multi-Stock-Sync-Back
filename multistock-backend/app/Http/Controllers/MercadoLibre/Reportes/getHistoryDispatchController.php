<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Exception;

class getHistoryDispatchController
{
    public function getHistoryDispatch($clientId, $productId)
    {
        try {
            set_time_limit(1000); // Extender tiempo máximo de ejecución

            $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

            if (!$credentials) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron credenciales válidas.',
                ], 404);
            }

            if ($credentials->isTokenExpired()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El token ha expirado.',
                ], 401);
            }

            // Obtener userId del vendedor
            $response = Http::withToken($credentials->access_token)
                ->get('https://api.mercadolibre.com/users/me');

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo obtener el ID del usuario.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $userId = $response->json()['id'] ?? null;

            if (!$userId) {
                throw new Exception('El ID del usuario no está definido.');
            }

            // Variables de paginación
            $allSales = [];
            $offset = 0;
            $limit = 50;

            // Obtener todas las órdenes pagadas filtradas por producto
            do {
                $response = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/orders/search", [
                        'seller' => $userId,
                        'order.status' => 'paid',
                        'limit' => $limit,
                        'offset' => $offset,
                        "sort" => "date_desc",
                    ]);

                if ($response->failed()) {
                    throw new Exception('Error al conectar con la API: ' . json_encode($response->json()));
                }

                $orders = $response->json()['results'] ?? [];

                // Filtrar las órdenes para incluir solo las que contienen el producto específico
                foreach ($orders as $order) {
                    foreach ($order['order_items'] as $item) {
                        if ($item['item']['id'] == $productId) {
                            $allSales[] = $order;
                            break; // Salta al siguiente pedido si ya encontró el producto específico
                        }
                    }
                }

                $offset += $limit;

            } while (count($orders) == $limit);

            // Procesar las órdenes para extraer datos relevantes
            $shippingDetails = [];
            $maxShipments = 500; // Límite de 500 envíos
            $shipmentCount = 0;

            // Estructura para evitar duplicados por shipping_id
            $processedShipments = [];

            foreach ($allSales as $order) {
                $shippingId = $order['shipping']['id'] ?? null;

                if (!$shippingId || isset($processedShipments[$shippingId])) {
                    continue; // Saltar si ya procesamos este envío
                }

                // Obtener ID del cliente (buyer)
                $customerId = $order['buyer']['id'] ?? 'No disponible';

                // Inicializar cantidad total de productos en esta orden
                $totalItems = 0;

                foreach ($order['order_items'] as $item) {
                    if ($item['item']['id'] == $productId) {
                        $totalItems += $item['quantity'] ?? 1;
                    }
                }

                if ($shipmentCount >= $maxShipments) {
                    break;
                }

                // Obtener los detalles de envío
                $responseShipping = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/shipments/{$shippingId}");

                if ($responseShipping->successful()) {
                    $shippingData = $responseShipping->json();

                    // Fecha de envío
                    $dateShipped = isset($shippingData['status_history']['date_shipped'])
                        ? date('Y-m-d H:i:s', strtotime($shippingData['status_history']['date_shipped']))
                        : 'No disponible';

                    // Guardar detalles del envío
                    $shippingDetails[] = [
                        'shipping_id' => $shippingData['id'] ?? 'No disponible',
                        'status' => $shippingData['status'] ?? 'Desconocido',
                        'tracking_number' => $shippingData['tracking_number'] ?? 'No disponible',
                        'date_shipped' => $dateShipped,
                        'total_items' => $totalItems, // Cantidad total de productos en la orden
                        'customer_id' => $customerId,  // ID del cliente
                    ];

                    // Marcar este shipping_id como procesado
                    $processedShipments[$shippingId] = true;
                    $shipmentCount++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Historial de despachos del producto',
                'data' => $shippingDetails,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Se produjo un error.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
