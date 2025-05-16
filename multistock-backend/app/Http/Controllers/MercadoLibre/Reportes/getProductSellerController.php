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
                "catalog_listing" => $productData['catalog_listing'],
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