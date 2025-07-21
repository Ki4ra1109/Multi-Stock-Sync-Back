<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Company;

class getHistoryDispatchController
{
    public function getHistoryDispatch($companyId, $skuSearch)
    {
        try {
            set_time_limit(1000);
            $startTime = microtime(true);

            // 1. Buscar la compañía y obtener el client_id
            $company = Company::find($companyId);
            if (!$company || !$company->client_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró la compañía o no tiene client_id asociado.',
                ], 404);
            }
            $clientId = $company->client_id;

            // 2. Cachear credenciales por 10 minutos
            $cacheKey = 'ml_credentials_' . $clientId;
            $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
                Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
                return MercadoLibreCredential::where('client_id', $clientId)->first();
            });

            if (!$credentials) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
                ], 404);
            }
            if ($credentials->isTokenExpired()) return response()->json(['status' => 'error', 'message' => 'El token ha expirado.'], 401);

            $response = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');
            if ($response->failed()) {
                return response()->json(['status' => 'error', 'message' => 'No se pudo obtener el ID del usuario.', 'error' => $response->json()], $response->status());
            }

            $userId = $response->json()['id'] ?? null;
            if (!$userId) throw new Exception('El ID del usuario no está definido.');

            // Paso 1: buscar producto que coincida con el SKU
            $productId = null;
            $productCache = [];
            $offset = 0;
            $limit = 50;
            $allSales = [];

            do {
                $response = Http::withToken($credentials->access_token)->get("https://api.mercadolibre.com/orders/search", [
                    'seller' => $userId,
                    'order.status' => 'paid',
                    'limit' => $limit,
                    'offset' => $offset,
                    'sort' => 'date_desc',
                ]);

                if ($response->failed()) throw new Exception('Error al buscar órdenes: ' . json_encode($response->json()));
                $orders = $response->json()['results'] ?? [];

                foreach ($orders as $order) {
                    foreach ($order['order_items'] as $item) {
                        $itemId = $item['item']['id'];
                        if (!isset($productCache[$itemId])) {
                            $productResponse = Http::withToken($credentials->access_token)->get("https://api.mercadolibre.com/items/{$itemId}");
                            if ($productResponse->successful()) {
                                $productCache[$itemId] = $productResponse->json();
                            }
                        }

                        $productData = $productCache[$itemId] ?? null;
                        if ($productData) {
                            $skuInfo = $this->obtenerSkuDesdeProducto($item, $productData);
                            if ($skuInfo['sku'] === $skuSearch) {
                                $productId = $itemId;
                                break 2;
                            }
                        }
                    }
                }

                $offset += $limit;
            } while (count($orders) === $limit && !$productId);

            if (!$productId) {
                return response()->json(['status' => 'error', 'message' => 'No se encontró producto con el SKU ingresado.'], 404);
            }

            // Paso 2: si la búsqueda tomó demasiado tiempo, fallback a búsqueda por ID
            $elapsedTime = microtime(true) - $startTime;
            if ($elapsedTime > 10) {
                return $this->getHistoryDispatchByProductId($clientId, $productId);
            }

            // Paso 3: buscar órdenes asociadas al productId
            return $this->getHistoryDispatchByProductId($clientId, $productId);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Se produjo un error.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getHistoryDispatchByProductId($clientId, $productId)
    {
        try {
            set_time_limit(1000);

            $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
            if (!$credentials) return response()->json(['status' => 'error', 'message' => 'No se encontraron credenciales válidas.'], 404);
            if ($credentials->isTokenExpired()) return response()->json(['status' => 'error', 'message' => 'El token ha expirado.'], 401);

            $response = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');
            if ($response->failed()) {
                return response()->json(['status' => 'error', 'message' => 'No se pudo obtener el ID del usuario.', 'error' => $response->json()], $response->status());
            }

            $userId = $response->json()['id'] ?? null;
            if (!$userId) throw new Exception('El ID del usuario no está definido.');

            $offset = 0;
            $limit = 50;
            $allSales = [];

            do {
                $response = Http::withToken($credentials->access_token)->get("https://api.mercadolibre.com/orders/search", [
                    'seller' => $userId,
                    'order.status' => 'paid',
                    'limit' => $limit,
                    'offset' => $offset,
                    'sort' => 'date_desc',
                ]);

                if ($response->failed()) throw new Exception('Error al obtener órdenes: ' . json_encode($response->json()));
                $orders = $response->json()['results'] ?? [];

                foreach ($orders as $order) {
                    foreach ($order['order_items'] as $item) {
                        if ($item['item']['id'] == $productId) {
                            $order['matched_item'] = $item;
                            $allSales[] = $order;
                            break;
                        }
                    }
                }

                $offset += $limit;
            } while (count($orders) === $limit);

            // Paso 4: obtener detalles de envíos
            $shippingDetails = [];
            $processedShipments = [];
            $shipmentCount = 0;
            $maxShipments = 50;

            foreach ($allSales as $order) {
                $item = $order['matched_item'];
                $itemId = $item['item']['id'];
                $item_quantity = $item["quantity"] ?? [];
                $shippingId = $order['shipping']['id'] ?? null;
                if (!$shippingId || isset($processedShipments[$shippingId])) continue;

                if ($shipmentCount >= $maxShipments) break;

                $shippingResponse = Http::withToken($credentials->access_token)->get("https://api.mercadolibre.com/shipments/{$shippingId}");

                if ($shippingResponse->successful()) {
                    $shippingData = $shippingResponse->json();

                    $dateShipped = isset($shippingData['status_history']['date_shipped'])
                        ? date('Y-m-d H:i:s', strtotime($shippingData['status_history']['date_shipped']))
                        : 'No disponible';

                    $dateEstimed = isset($shippingData['shipping_option']['estimated_delivery_time']["date"])
                        ? date('Y-m-d H:i:s', strtotime($shippingData['shipping_option']['estimated_delivery_time']["date"]))
                        : 'No disponible';

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

                    if (isset($shippingData['status'])) {
                        $shippingData['status'] = $translations[$shippingData['status']] ?? $shippingData['status'];
                    }

                    $shippingDetails[] = [
                        "Producto Id" => $itemId,
                        'shipping_id' => $shippingData['id'] ?? 'No disponible',
                        'status' => $shippingData['status'] ?? 'Desconocido',
                        'tracking_number' => $shippingData['tracking_number'] ?? 'No disponible',
                        'date_shipped' => $dateShipped,
                        "date_estimed_arrival" => $dateEstimed,
                        'total_items' => $item_quantity ?? [0],
                        'customer_id' => $order['buyer']['id'] ?? 'No disponible',
                    ];

                    $processedShipments[$shippingId] = true;
                    $shipmentCount++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Historial de despachos del producto',
                'data' => $shippingDetails,
                'resumen' => [
                    'total_despachos' => count($shippingDetails),
                    'primera_orden' => $shippingDetails[count($shippingDetails) - 1] ?? null,
                    'ultima_orden' => $shippingDetails[0] ?? null,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Se produjo un error en búsqueda por ID.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function obtenerSkuDesdeProducto($item, $productData)
    {
        $sku = $item['item']['seller_custom_field'] ?? null;
        $skuSource = 'seller_custom_field';

        if (empty($sku) && isset($productData['seller_sku'])) {
            $sku = $productData['seller_sku'];
            $skuSource = 'seller_sku';
        }

        if (empty($sku) && isset($productData['attributes'])) {
            foreach ($productData['attributes'] as $attribute) {
                if (in_array(strtolower($attribute['id']), ['sku', 'codigo', 'product_code', 'reference']) ||
                    in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia'])) {
                    $sku = $attribute['value_name'];
                    $skuSource = 'attributes';
                    break;
                }
            }
        }

        if (empty($sku)) {
            foreach ($productData['attributes'] ?? [] as $attribute) {
                if (strtolower($attribute['id']) === 'model' || strtolower($attribute['name']) === 'modelo') {
                    $sku = $attribute['value_name'];
                    $skuSource = 'model_fallback';
                    break;
                }
            }
        }

        return ['sku' => $sku, 'sku_source' => $skuSource];
    }
}
