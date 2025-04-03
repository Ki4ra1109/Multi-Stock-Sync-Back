<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getStockReceptionController
{
    public function getProductsToDispatch($clientId)
    {
        // ... este método no ha sido modificado
    }

    public function getStockReception($clientId)
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
        $receivedStock = [];

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $quantity = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $totalAmount = $quantity * $unitPrice;
                $dateCreated = $order['date_created'];
                $deliveredBy = $order['buyer']['nickname'];

                [$sku, $skuSource] = $this->resolveSku($item, $credentials);

                if (!isset($receivedStock[$productId])) {
                    $receivedStock[$productId] = [
                        'id' => $productId,
                        'title' => $item['item']['title'],
                        'sku' => $sku,
                        'sku_source' => $skuSource,
                        'quantity' => 0,
                        'unit_price' => $unitPrice,
                        'total_amount' => 0,
                        'date_created' => $dateCreated,
                        'delivered_by' => $deliveredBy,
                    ];
                }

                $receivedStock[$productId]['quantity'] += $quantity;
                $receivedStock[$productId]['total_amount'] += $totalAmount;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Recepción de stock obtenida con éxito.',
            'data' => array_values($receivedStock),
        ]);
    }

    /**
     * Lógica avanzada para obtener el SKU desde múltiples fuentes.
     */
    private function resolveSku(array $item, $credentials): array
    {
        $sku = null;
        $skuSource = 'not_found';
        $productId = $item['item']['id'];

        // 1. seller_custom_field del ítem
        $sku = $item['item']['seller_custom_field'] ?? null;
        if ($sku) {
            return [$sku, 'item_seller_custom_field'];
        }

        // 2. seller_sku del ítem
        if (isset($item['item']['seller_sku'])) {
            $sku = $item['item']['seller_sku'];
            if ($sku) {
                return [$sku, 'item_seller_sku'];
            }
        }

        // 3. Detalles del producto desde la API
        $productResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/items/{$productId}");

        if ($productResponse->failed()) {
            return ['No se encuentra disponible en mercado libre', 'not_found'];
        }

        $product = $productResponse->json();

        // 3. seller_sku del producto
        if (!empty($product['seller_sku'])) {
            return [$product['seller_sku'], 'product_seller_sku'];
        }

        // 4. Buscar en atributos
        foreach ($product['attributes'] ?? [] as $attribute) {
            if (in_array(strtolower($attribute['id']), ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) ||
                in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia', 'codigo', 'código de producto'])) {
                return [$attribute['value_name'] ?? 'No tiene SKU', 'product_attributes'];
            }
        }

        // 5. Fallback a modelo
        foreach ($product['attributes'] ?? [] as $attribute) {
            if (strtolower($attribute['id']) === 'model' || strtolower($attribute['name']) === 'modelo') {
                return [$attribute['value_name'] ?? 'No tiene SKU', 'model_fallback'];
            }
        }

        // 6. Si no se encuentra nada
        return ['No se encuentra disponible en mercado libre', 'not_found'];
    }
}
// 