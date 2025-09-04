<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class getProductSellerController extends Controller
{
    public function getProductSeller(Request $request, $client_id)
    {
        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $client_id;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client_id) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $client_id");
            return MercadoLibreCredential::where('client_id', $client_id)->first();
        });

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // ✅ Refrescar token automáticamente si está vencido
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

        // Obtener ID del usuario
        $userResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/me");

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener información del usuario.',
            ], 500);
        }

        $userId = $userResponse->json()['id'];
        $limit = intval($request->query('limit', 50));
        $offset = intval($request->query('offset', 0));
        $q = $request->query('q');

        // 🔍 Buscar por ID exacto si comienza con MLC
        if ($q && str_starts_with(strtoupper($q), 'MLC')) {
            $productResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$q}");

            if ($productResponse->ok()) {
                $productData = $productResponse->json();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Producto encontrado por ID.',
                    'products' => [[
                        'id' => $productData['id'],
                        'title' => $productData['title'],
                        'price' => $productData['price'],
                        'date_created' => $productData['date_created'],
                        'available_quantity' => $productData['available_quantity'],
                        'condition' => $productData['condition'],
                        'status' => $productData['status'],
                        'pictures' => $productData['pictures'],
                        'atributes' => $productData['attributes'],
                        'permalink' => $productData['permalink'],
                        'sku' => $productData['seller_custom_field'] ?? 'No disponible', // SKU principal
                        'variations' => collect($productData['variations'] ?? [])->map(function ($v) {
                            return [
                                'variation_id' => $v['id'],
                                'seller_custom_field' => $v['seller_custom_field'] ?? 'No disponible',
                                'seller_sku' => $v['seller_sku'] ?? 'No disponible',
                                'sku' => $v['seller_custom_field'] ?? 'No disponible', // puedes ajustar si tu lógica define otro campo
                                'available_quantity' => $v['available_quantity'],
                                'pictures' => $v['picture_ids'] ?? [],
                                'attribute_combinations' => $v['attribute_combinations'] ?? []
                            ];
                        })->toArray()
                    ]],
                    'cantidad_total' => 1,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'No se encontró producto con ese ID.',
                'products' => [],
                'cantidad_total' => 0,
            ]);
        }

        // 🔍 Buscar por texto o sin q
        $params = [
            'limit' => $limit,
            'offset' => $offset
        ];
        if ($q) $params['q'] = $q;

        $searchResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search", $params);

        if ($searchResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los productos.',
            ], 500);
        }

        $searchData = $searchResponse->json();
        $productIds = $searchData['results'];
        $total = $searchData['paging']['total'] ?? count($productIds);
        $allProducts = [];

        foreach ($productIds as $productId) {
            $productResponse = Http::withToken($credentials->access_token)
                 ->get("https://api.mercadolibre.com/items/{$productId}?include_attributes=all");

            if ($productResponse->ok()) {
                $productData = $productResponse->json();

                $sku = $productData['seller_custom_field'] ?? 'No disponible';

                $allProducts[] = [
                    'id' => $productData['id'],
                    'title' => $productData['title'],
                    'price' => $productData['price'],
                    'date_created' => $productData['date_created'],
                    'available_quantity' => $productData['available_quantity'],
                    'condition' => $productData['condition'],
                    'status' => $productData['status'],
                    'pictures' => $productData['pictures'],
                    'attributes' => $productData['attributes'], 
                    'permalink' => $productData['permalink'],
                    'sku' => $productData['seller_custom_field'] ?? 'No disponible', // SKU principal
                        'variations' => collect($productData['variations'] ?? [])->map(function ($v) {
                            return [
                                'variation_id' => $v['id'],
                                'seller_custom_field' => $v['seller_custom_field'] ?? 'No disponible',
                                'seller_sku' => $v['seller_sku'] ?? 'No disponible',
                                'sku' => $v['seller_custom_field'] ?? 'No disponible', // puedes ajustar si tu lógica define otro campo
                                'available_quantity' => $v['available_quantity'],
                                'pictures' => $v['picture_ids'] ?? [],
                                'attribute_combinations' => $v['attribute_combinations'] ?? []
                            ];
                        })->toArray()
                            ];
            }
        }

        // Ordenar por fecha descendente (más reciente primero)
        usort($allProducts, function ($a, $b) {
            return strtotime($b['date_created']) - strtotime($a['date_created']);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Productos obtenidos correctamente.',
            'cantidad_total' => $total,
            'cantidad_mostrada' => count($allProducts),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $allProducts,
        ], 200);
    }

    public function updateSku(Request $request, $client_id, $item_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe enviar el campo sku.',
            ], 400);
        }

        $url = "https://api.mercadolibre.com/items/{$item_id}";
        $payload = [
            'seller_custom_field' => $sku
        ];

        $response = Http::withToken($credentials->access_token)
            ->withHeaders(['Accept' => 'application/json'])
            ->put($url, $payload);

        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'message' => 'SKU actualizado correctamente.',
                'company_id' => $client_id,
                'item_id' => $item_id,
                'sku' => $sku,
            ]);
        } else {
            $error = $response->json();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar SKU.',
                'company_id' => $client_id,
                'item_id' => $item_id,
                'error' => $error['message'] ?? 'Error desconocido',
            ], $response->status());
        }
    }
}
