<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class getProductSellerController{

    public function getProductSeller(Request $request, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

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
            ->get("https://api.mercadolibre.com/users/me");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener información del usuario.',
            ], 500);
        }

        $userId = $response->json()['id'];
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 100);

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los productos.',
            ], 500);
        }

        $productIds = $response->json()['results'];

        $allProducts = [];

        foreach ($productIds as $productId) {
            $productResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$productId}");

            if ($productResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Error al obtener información del producto: $productId",
                ], 500);
            }

            $productData = $productResponse->json();
            $allProducts[] = [
                'id' => $productData['id'],
                'title' => $productData['title'],
                'price' => $productData['price'],
                'available_quantity' => $productData['available_quantity'],
                'condition' => $productData['condition'],
                'status' => $productData['status'],
                'pictures' => $productData['pictures'],
                "atributes" => $productData['attributes'],
            ];
        }

        return response()->json([
            'status' => 'success',
            "message" => 'Productos obtenidos correctamente.',
            "cantidad" => count($allProducts),
            'products' => $allProducts,
        ], 200);
    }

}