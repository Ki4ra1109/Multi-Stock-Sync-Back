<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class ProductReportController
{
    /**
     * Obtiene productos de MercadoLibre usando client_id con filtro de estado de pago.
     */
    public function listProductsByClientIdWithPaymentStatus($clientId, Request $request)
    {
        try {
            // Cachear credenciales por 10 minutos
            $cacheKey = 'ml_credentials_' . $clientId;
            $credentials = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
                \Illuminate\Support\Facades\Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
                return \App\Models\MercadoLibreCredential::where('client_id', $clientId)->first();
            });

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

            // Obtener ID de usuario
            $userResponse = Http::withToken($credentials->access_token)
                ->get('https://api.mercadolibre.com/users/me');

            if ($userResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo obtener el ID del usuario. Verifique su token.',
                    'error' => $userResponse->json(),
                ], 500);
            }

            $userId = $userResponse->json()['id'];

            // Parámetros de paginación y filtro
            $limit = $request->query('limit', 50);
            $offset = $request->query('offset', 0);
            $paymentStatus = $request->query('payment_status', null);

            // Obtener lista de productos
            $itemsResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if ($itemsResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al obtener productos de MercadoLibre.',
                    'error' => $itemsResponse->json(),
                ], $itemsResponse->status());
            }

            $productIds = $itemsResponse->json()['results'];
            $total = $itemsResponse->json()['paging']['total'];

            if (empty($productIds)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No se encontraron productos.',
                    'data' => [],
                    'pagination' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ]);
            }

            // Obtener detalles de todos los productos en una sola solicitud
            $productsResponse = Http::withToken($credentials->access_token)
                ->get('https://api.mercadolibre.com/items', [
                    'ids' => implode(',', $productIds),
                ]);

            if ($productsResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al obtener detalles de productos.',
                    'error' => $productsResponse->json(),
                ], $productsResponse->status());
            }

            $productsData = $productsResponse->json();

            $products = [];
            foreach ($productsData as $product) {
                $productData = $product['body'] ?? [];

                if (empty($productData)) {
                    continue;
                }

                // Obtener nombre de la categoría
                $categoryName = 'Desconocida';
                if (!empty($productData['category_id'])) {
                    $categoryResponse = Http::get("https://api.mercadolibre.com/categories/{$productData['category_id']}");
                    if ($categoryResponse->successful()) {
                        $categoryName = $categoryResponse->json()['name'] ?? 'Desconocida';
                    }
                }

                // Filtrar por estado de pago si se proporcionó
                if ($paymentStatus && ($productData['status'] !== $paymentStatus)) {
                    continue;
                }

                $products[] = [
                    'id' => $productData['id'],
                    'title' => $productData['title'],
                    'price' => $productData['price'],
                    'currency_id' => $productData['currency_id'],
                    'available_quantity' => $productData['available_quantity'],
                    'sold_quantity' => $productData['sold_quantity'],
                    'thumbnail' => $productData['thumbnail'],
                    'permalink' => $productData['permalink'],
                    'status' => $productData['status'],
                    'category_id' => $productData['category_id'],
                    'category_name' => $categoryName,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Productos obtenidos con éxito.',
                'data' => $products,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error inesperado.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
