<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getTopSellingProductsController
{
    public function getTopSellingProducts($clientId)
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
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=paid&order.date_created.from={$dateFrom}&order.date_created.to={$dateTo}");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $productSales = [];
        $totalSales = 0;

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $variationId = $item['item']['variation_id'] ?? null;
                $size = null;
                $skuSource = 'not_found';
                
                // 1. Primero buscar en seller_custom_field del ítem del pedido
                $sku = $item['item']['seller_custom_field'] ?? null;
                
                // 2. Si no está, buscar en seller_sku del ítem del pedido
                if (empty($sku)) {
                    $sku = $item['item']['seller_sku'] ?? null;
                    if ($sku) {
                        $skuSource = 'item_seller_sku';
                    }
                } else {
                    $skuSource = 'item_seller_custom_field';
                }

                $productDetailsResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/items/{$productId}");

                if ($productDetailsResponse->successful()) {
                    $productData = $productDetailsResponse->json();
                    
                    // 3. Si no se encontró en el ítem, buscar en seller_sku del producto
                    if (empty($sku)) {
                        if (isset($productData['seller_sku'])) {
                            $sku = $productData['seller_sku'];
                            $skuSource = 'product_seller_sku';
                        }
                    }

                    // 4. Si aún no se encontró, buscar en los atributos del producto
                    if (empty($sku) && isset($productData['attributes'])) {
                        foreach ($productData['attributes'] as $attribute) {
                            if (in_array(strtolower($attribute['id']), ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) || 
                                in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia', 'codigo', 'código de producto'])) {
                                $sku = $attribute['value_name'];
                                $skuSource = 'product_attributes';
                                break;
                            }
                        }
                    }

                    // 5. Si sigue sin encontrarse, intentar con el modelo como último recurso
                    if (empty($sku) && isset($productData['attributes'])) {
                        foreach ($productData['attributes'] as $attribute) {
                            if (strtolower($attribute['id']) === 'model' || 
                                strtolower($attribute['name']) === 'modelo') {
                                $sku = 'MOD-' . $attribute['value_name'];
                                $skuSource = 'model_fallback';
                                break;
                            }
                        }
                    }

                    // 6. Establecer mensaje predeterminado si no se encontró SKU
                    if (empty($sku)) {
                        $sku = 'No se encuentra disponible en mercado libre';
                    }

                    // Manejo de variaciones (tamaño)
                    if ($variationId) {
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

                    if (!isset($productSales[$productId])) {
                        $productSales[$productId] = [
                            'id' => $productId,
                            'variation_id' => $variationId,
                            'title' => $item['item']['title'],
                            'sku' => $sku,
                            'sku_source' => $skuSource,
                            'quantity' => 0,
                            'total_amount' => 0,
                            'size' => $size,
                            'variation_attributes' => $productData['attributes'],
                        ];
                    }

                    $productSales[$productId]['quantity'] += $item['quantity'];
                    $productSales[$productId]['total_amount'] += $item['quantity'] * $item['unit_price'];
                    $totalSales += $item['quantity'] * $item['unit_price'];
                }
            }
        }

        usort($productSales, function ($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });

        $totalProducts = count($productSales);
        $totalPages = ceil($totalProducts / $perPage);
        $offset = ($page - 1) * $perPage;
        $productSales = array_slice($productSales, $offset, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Productos más vendidos obtenidos con éxito.',
            'total_sales' => $totalSales,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'data' => $productSales,
        ]);
    }
}