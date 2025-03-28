<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Exception;

class getHistoryDispatchController
{
    public function getHistoryDispatch($clientId, $productId, $skuSearch)
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
                        if ($item['item']['id'] === $productId) {
                            $allSales[] = $order;
                            break;
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
                $variationId = $order['order_items']['item']['variation_id'] ?? 'N/A';
                $item_quantity = $order['order_items'][0]["quantity"] ?? [];
                $shippingId = $order['shipping']['id'] ?? null;
                if (!$shippingId || isset($processedShipments[$shippingId])) {
                    continue; // Saltar si ya procesamos este envío
                }

                // Obtener ID del cliente (buyer)
                $customerId = $order['buyer']['id'] ?? 'No disponible';

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
                    
                    // Traducir estado del envío si existe
                    if (isset($shippingData['status'])) {
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

                        $shippingData['status'] = $translations[$shippingData['status']] ?? $shippingData['status'];
                    }

                    $productDetailsResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/items/{$productId}");

                    //Durante el siguiente bloque de código, se busca el SKU en diferentes lugares
                    // 1. Primero buscar en seller_custom_field del ítem del pedido
                    // 2. Si no está, buscar en seller_sku del ítem del pedido
                    // 3. Si aún no se encontró, buscar en los atributos del producto
                    // 4. Si sigue sin encontrarse, intentar con el modelo como último recurso
                    // 5. Despues de todo esto, se comprueba el estado del sku y se guarda el despacho.

                    if($productDetailsResponse->successful()){
                        $productData = $productDetailsResponse->json();

                        $sku = $item['item']['seller_custom_field'] ?? null;
                        $skuSource = 'not_found';

                        if (empty($sku)) {
                            if (isset($productData['seller_sku'])) {
                                $sku = $productData['seller_sku'];
                                $skuSource = 'seller_sku';
                                if ($sku != $skuSearch) {
                                    $sku = null;
                                }
                            }
                        } else {
                            $skuSource = 'seller_custom_field';
                        }

                        if (empty($sku) && isset($productData['attributes'])) {
                            foreach ($productData['attributes'] as $attribute) {
                                if (in_array(strtolower($attribute['id']), ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) || 
                                    in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia', 'codigo', 'código de producto'])) {
                                    $sku = $attribute['value_name'];
                                    if ($sku != $skuSearch) {
                                        $sku = null;
                                    }
                                    $skuSource = 'attributes';
                                    break;
                                }
                            }
                        }

                        if (empty($sku) && isset($productData['attributes'])) {
                            foreach ($productData['attributes'] as $attribute) {
                                if (strtolower($attribute['id']) === 'model' || 
                                    strtolower($attribute['name']) === 'modelo') {
                                    $sku = $attribute['value_name'];
                                    if ($sku != $skuSearch) {
                                        $sku = null;
                                    }
                                    $skuSource = 'model_fallback';
                                    break;
                                }
                            }
                        }

                        if (empty($sku) === true) {
                            $shippingDetails[] = [
                                'shipping_id' => $shippingData['id'] ?? 'No disponible',
                                'status' => $shippingData['status'] ?? 'Desconocido',
                                'tracking_number' => $shippingData['tracking_number'] ?? 'No disponible',
                                'date_shipped' => $dateShipped,
                                'total_items' => $item_quantity ?? [0],
                                'customer_id' => $customerId,  // ID del cliente
                                "sku" => $skuSearch ?? 'No disponible',
                            ];
                        }else{
                            $shippingDetails[] = [
                                'shipping_id' => $shippingData['id'] ?? 'No disponible',
                                'status' => $shippingData['status'] ?? 'Desconocido',
                                'tracking_number' => $shippingData['tracking_number'] ?? 'No disponible',
                                'date_shipped' => $dateShipped,
                                'total_items' => $item_quantity ?? [0],
                                'customer_id' => $customerId,  // ID del cliente
                                "sku" => $sku ?? 'No disponible',
                            ];
                        }
                    
                    }
                    // Marcar este shipping_id como procesado
                    $processedShipments[$shippingId] = true;
                    $shipmentCount++;
                }
            }

            //Retorna los resultados
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