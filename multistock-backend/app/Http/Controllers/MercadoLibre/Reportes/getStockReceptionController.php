<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getStockReceptionController
{
    public function getProductsToDispatch($clientId)
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

        // Definir la fecha de búsqueda (por defecto, el mes actual)
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

        // Llamada a la API de MercadoLibre para obtener los pedidos pendientes de despacho
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search?seller={$userId}&order.status=ready_to_ship&order.date_created.from={$dateFrom}&order.date_created.to={$dateTo}");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $productsToDispatch = [];

        foreach ($orders as $order) {
            foreach ($order['order_items'] as $item) {
                $productId = $item['item']['id'];
                $variationId = $item['item']['variation_id'] ?? null;
                $size = null;

                // Obtener detalles del producto para encontrar la talla
                $productDetailsResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/items/{$productId}");

                if ($productDetailsResponse->successful()) {
                    $productData = $productDetailsResponse->json();

                    // Si hay una variante específica, obtener la información de la variante
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

                    if (!isset($productsToDispatch[$productId])) {
                        $productsToDispatch[$productId] = [
                            'id' => $productId,
                            'variation_id' => $variationId,
                            'title' => $item['item']['title'],
                            'quantity' => 0,
                            'size' => $size,
                        ];
                    }

                    $productsToDispatch[$productId]['quantity'] += $item['quantity'];
                }
            }
        }

        usort($productsToDispatch, function ($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });

        $totalProducts = count($productsToDispatch);
        $totalPages = ceil($totalProducts / $perPage);
        $offset = ($page - 1) * $perPage;
        $productsToDispatch = array_slice($productsToDispatch, $offset, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Productos pendientes de despacho obtenidos con éxito.',
            'current_page' => $page,
            'total_pages' => $totalPages,
            'data' => $productsToDispatch,
        ]);
    }
}