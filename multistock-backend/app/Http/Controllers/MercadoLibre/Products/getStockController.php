<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getStockController
{
    public function getStock($clientId, $year = null, $month = null, $day = null)
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

        $url = "https://api.mercadolibre.com/users/{$userId}/items/search";
        $queryParams = [];

        if ($year) {
            $queryParams['year'] = $year;
        }
        if ($month) {
            $queryParams['month'] = $month;
        }
        if ($day) {
            $queryParams['day'] = $day;
        }

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $response = Http::withToken($credentials->access_token)
            ->get($url);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $items = $response->json()['results'];
        $productsStock = [];

        foreach ($items as $itemId) {
            $itemResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$itemId}");

            if ($itemResponse->successful()) {
                $itemData = $itemResponse->json();
                
                // 1. Primero buscar en seller_custom_field del ítem
                $sku = $itemData['seller_custom_field'] ?? null;
                $skuSource = 'not_found';
                
                // 2. Si no está, buscar en seller_sku del producto
                if (empty($sku)) {
                    if (isset($itemData['seller_sku'])) {
                        $sku = $itemData['seller_sku'];
                        $skuSource = 'seller_sku';
                    }
                } else {
                    $skuSource = 'seller_custom_field';
                }

                // 3. Si aún no se encontró, buscar en los atributos del producto
                if (empty($sku) && isset($itemData['attributes'])) {
                    foreach ($itemData['attributes'] as $attribute) {
                        // Buscar por ID o nombre de atributo común para SKUs
                        if (in_array(strtolower($attribute['id']), ['seller_sku', 'sku', 'codigo', 'reference', 'product_code']) || 
                            in_array(strtolower($attribute['name']), ['sku', 'código', 'referencia', 'codigo', 'código de producto'])) {
                            $sku = $attribute['value_name'];
                            $skuSource = 'attributes';
                            break;
                        }
                    }
                }

                // 4. Si sigue sin encontrarse, intentar con el modelo como último recurso
                if (empty($sku) && isset($itemData['attributes'])) {
                    foreach ($itemData['attributes'] as $attribute) {
                        if (strtolower($attribute['id']) === 'model' || 
                            strtolower($attribute['name']) === 'modelo') {
                            $sku = 'MOD-' . $attribute['value_name'];
                            $skuSource = 'model_fallback';
                            break;
                        }
                    }
                }

                // 5. Establecer mensaje predeterminado si no se encontró SKU
                if (empty($sku)) {
                    $sku = 'No se encuentra disponible en mercado libre';
                }

                $productsStock[] = [
                    'id' => $itemData['id'],
                    'title' => $itemData['title'],
                    'available_quantity' => $itemData['available_quantity'],
                    'stock_reload_date' => $itemData['date_created'],
                    'purchase_sale_date' => $itemData['last_updated'],
                    'sku' => $sku,
                    'details' => $itemData['attributes'],
                    'sku_source' => $skuSource,
                    'sku_missing_reason' => $skuSource === 'not_found' ? 
                        'No se encontraron campos seller_custom_field, seller_sku ni atributos SKU en el producto' : null,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Stock de productos obtenidos con éxito.',
            'data' => $productsStock,
        ]);
    }
}