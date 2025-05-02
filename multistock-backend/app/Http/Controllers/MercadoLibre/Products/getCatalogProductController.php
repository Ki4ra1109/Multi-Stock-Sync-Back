<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class getCatalogProductController {

    public function getCatalogProducts(Request $request, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();
    
        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }
    
        $categoryId = $request->query('category_id');
        $familyName = $request->query('family_name');
    
        if (!$categoryId) {
            return response()->json(['status' => 'error', 'message' => 'Falta category_id'], 422);
        }
    
        $endpoint = 'https://api.mercadolibre.com/catalog_products/search';
        $params = [
            'category_id' => $categoryId,
            'limit' => 20
        ];
    
        if ($familyName) {
            $endpoint = 'https://api.mercadolibre.com/products/search';
            $params = [
                'category_id' => $categoryId,
                'family_id' => $familyName
            ];
        } else {
            $params['q'] = 'producto'; 
        }
    
        $response = Http::withToken($credentials->access_token)->get($endpoint, $params);
    
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener productos del catálogo',
                'ml_error' => $response->json()
            ], $response->status());
        }
    
        return response()->json([
            'status' => 'success',
            'products' => $response->json()['results'] ?? []
        ]);
    }
}