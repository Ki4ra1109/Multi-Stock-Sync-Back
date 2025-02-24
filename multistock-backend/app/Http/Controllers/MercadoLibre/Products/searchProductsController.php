<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class searchProductsController extends Controller
{
    protected $mercadoLibreQueries;

    public function __construct()
    {
        // No need for MercadoLibreQueries dependency
    }

    /**
     * Search products from MercadoLibre API using client_id and search term.
     */
    public function searchProducts($clientId, Request $request)
    {
        // Get credentials by client_id
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        // Check if credentials exist
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Check if token is expired
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        // Get user information from token
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                'error' => $userResponse->json(),
            ], 500);
        }

        $userData = $userResponse->json();
        $userId = $userData['id'];
        $userNickname = $userData['nickname'];

        // Get query parameters
        $searchTerm = $request->query('q', ''); // Search term
        $limit = $request->query('limit', 50); // Default limit to 50
        $offset = $request->query('offset', 0); // Default offset to 0

        // API request to search products with search term, limit, and offset
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/sites/MLC/search", [
                'q' => $searchTerm,
                'seller_id' => $userId,
                'limit' => $limit,
                'offset' => $offset,
            ]);

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Get product data and total count
        $products = $response->json()['results'];
        $total = $response->json()['paging']['total'];

        // Calculate pagination details
        $totalPages = ceil($total / $limit);
        $currentPage = ($offset / $limit) + 1;

        // Enhance products with status by fetching detailed information
        $enhancedProducts = [];
        foreach ($products as $product) {
            $productResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$product['id']}");

            if ($productResponse->successful()) {
                $productData = $productResponse->json();
                $enhancedProducts[] = [
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
                ];
            }
        }

        // Return products data with pagination info and user details
        return response()->json([
            'status' => 'success',
            'message' => 'Productos obtenidos con éxito.',
            'client' => [
                'id' => $userId,
                'nickname' => $userNickname,
                'email' => $userData['email'] ?? 'N/A',
                'country_id' => $userData['country_id'],
            ],
            'data' => $enhancedProducts,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
            ],
        ]);
    }
}