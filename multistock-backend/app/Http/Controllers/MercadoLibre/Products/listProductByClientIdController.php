<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class listProductByClientIdController
{
    /**
     * Get products from MercadoLibre API using client_id.
     */
    public function listProductsByClientId($clientId)
    {
        // Diccionario de estados traducidos
        $statusDictionary = [
            'active' => 'Activo',
            'paused' => 'Pausado',
            'closed' => 'Cerrado',
            'under_review' => 'En revisión',
            'inactive' => 'Inactivo',
            'payment_required' => 'Pago requerido',
            'not_yet_active' => 'Aún no activo',
            'deleted' => 'Eliminado',
        ];

        // Obtener credenciales por client_id
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        // Validar si existen credenciales
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Verificar si el token ha expirado
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        // Obtener el ID del usuario en MercadoLibre
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

        // Obtener productos sin paginación
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Obtener los IDs de productos y el total
        $productIds = $response->json()['results'];
        $total = $response->json()['paging']['total'];

        // Obtener detalles de los productos
        $products = [];
        foreach ($productIds as $productId) {
            $productResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$productId}");

            if ($productResponse->successful()) {
                $productData = $productResponse->json();

                // Obtener nombre de la categoría
                $categoryName = 'Desconocida';
                if (!empty($productData['category_id'])) {
                    $categoryResponse = Http::get("https://api.mercadolibre.com/categories/{$productData['category_id']}");
                    if ($categoryResponse->successful()) {
                        $categoryName = $categoryResponse->json()['name'] ?? 'Desconocida';
                    }
                }

                // Traducir estado si existe en el diccionario
                $translatedStatus = $statusDictionary[$productData['status']] ?? $productData['status'];

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
                    'status_translated' => $translatedStatus,
                    'category_id' => $productData['category_id'],
                    'category_name' => $categoryName,
                ];
            }
        }

        // Retornar los productos
        return response()->json([
            'status' => 'success',
            'message' => 'Productos obtenidos con éxito.',
            'data' => $products,
            'total' => $total,
        ]);
    }
}
