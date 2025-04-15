<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class searchProductsController extends Controller
{
    public function searchProducts($clientId, Request $request)
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
        $searchTerm = $request->query('q', '');
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                'q' => $searchTerm,
                'limit' => $limit,
                'offset' => $offset,
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $productIds = $response->json()['results'];
        $total = $response->json()['paging']['total'];
        $products = [];

        foreach ($productIds as $productId) {
            $productResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$productId}");

            if ($productResponse->successful()) {
                $productData = $productResponse->json();

                $categoryName = 'Desconocida';
                if (!empty($productData['category_id'])) {
                    $categoryResponse = Http::get("https://api.mercadolibre.com/categories/{$productData['category_id']}");
                    if ($categoryResponse->successful()) {
                        $categoryName = $categoryResponse->json()['name'] ?? 'Desconocida';
                    }
                }

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

        return response()->json([
            'status' => 'success',
            'message' => 'Productos obtenidos con éxito.',
            'data' => $products,
            'total' => $total,
        ]);
    }
}
