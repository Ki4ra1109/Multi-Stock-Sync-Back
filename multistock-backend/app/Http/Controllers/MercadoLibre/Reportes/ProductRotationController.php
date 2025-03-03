<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MercadoLibreCredential;

class productRotationController extends Controller
{
    /**
     * Get products with the highest and lowest rotation.
     */
    public function getProductRotation(Request $request, $clientId)
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

        // Get user id from token
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

        // Get all products for the seller
        $productsResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/items/search?seller=$userId");

        if ($productsResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los productos.',
                'error' => $productsResponse->json(),
            ], 500);
        }

        $products = $productsResponse->json()['results'];

        // Initialize the product data array
        $productRotations = [];

        foreach ($products as $product) {
            // Get product stock
            $stockResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$product['id']}");

            if ($stockResponse->failed()) {
                continue; // Skip this product if we can't get stock data
            }

            $stock = $stockResponse->json()['available_quantity'];

            // Get sales data for this product (example: using orders API or custom analytics)
            $salesResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/orders/search?seller=$userId&order.status=paid&item_id={$product['id']}");

            if ($salesResponse->failed()) {
                continue; // Skip this product if we can't get sales data
            }

            $sales = count($salesResponse->json()['results']); // Assuming each order has one product

            // Calculate product rotation
            $rotation = $stock > 0 ? $sales / $stock : 0; // Avoid division by zero

            $productRotations[] = [
                'product_id' => $product['id'],
                'name' => $product['title'],
                'stock' => $stock,
                'sales' => $sales,
                'rotation' => $rotation
            ];
        }

        // Sort products by rotation (highest to lowest)
        usort($productRotations, function($a, $b) {
            return $b['rotation'] <=> $a['rotation']; // Sorting in descending order
        });

        // Get the top 5 products with highest rotation
        $highestRotation = array_slice($productRotations, 0, 5);

        // Return the data in the response
        return response()->json([
            'status' => 'success',
            'message' => 'Productos con mayor y menor rotación obtenidos con éxito.',
            'data' => [
                'highest_rotation' => $highestRotation,
                'lowest_rotation' => $lowestRotation
            ]
        ]);
    }
}