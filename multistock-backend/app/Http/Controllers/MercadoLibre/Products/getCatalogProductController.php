<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class getCatalogProductController extends Controller
{
    public function getCatalogProducts(Request $request, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials) {
            return response()->json(['status' => 'error', 'message' => 'Credenciales no encontradas.'], 404);
        }

        // Refrescar token si está expirado
        if ($credentials->isTokenExpired()) {
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials->client_id,
                'client_secret' => $credentials->client_secret,
                'refresh_token' => $credentials->refresh_token,
            ]);

            if ($refreshResponse->failed()) {
                return response()->json(['status' => 'error', 'message' => 'No se pudo refrescar el token.'], 401);
            }

            $data = $refreshResponse->json();

            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }

        $title = $request->query('title');

        if (!$title) {
            return response()->json(['status' => 'error', 'message' => 'Falta el parámetro title'], 422);
        }

        // 1. Predicción de categoría
        $prediction = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/sites/MLC/domain_discovery/search', [
            'q' => $title,
            'limit' => 1
        ]);

        if ($prediction->failed() || empty($prediction->json())) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo predecir la categoría desde el título.',
                'ml_error' => $prediction->json()
            ], $prediction->status());
        }

        $data = $prediction->json()[0];

        $categoryId = $data['category_id'] ?? null;
        $categoryName = $data['category_name'] ?? null;
        $domainId = $data['domain_id'] ?? null;
        $domainName = $data['domain_name'] ?? null;
        $familyId = $data['family_id'] ?? null;
        $familyName = null;
        $catalogProducts = [];

        // 2. Si hay family_id, buscar productos de catálogo
        if ($familyId) {
            $productsResponse = Http::withToken($credentials->access_token)
                ->get('https://api.mercadolibre.com/products/search', [
                'category_id' => $categoryId,
                'family_id' => $familyId
            ]);

            if ($productsResponse->ok()) {
                $catalogProducts = $productsResponse->json()['results'] ?? [];

                // 3. Obtener nombre de la familia desde el primer producto (si hay)
                if (!empty($catalogProducts)) {
                    $firstProductId = $catalogProducts[0];
                    $productDetail = Http::withToken($credentials->access_token)
                        ->get("https://api.mercadolibre.com/products/{$firstProductId}");

                    if ($productDetail->ok()) {
                        $familyName = $productDetail->json()['name'] ?? null;
                    }
                }
            }
        }

        // 4. Si no hay family_name, usar domain_name como alternativa
        if (!$familyName && $domainName) {
            $familyName = $domainName;
        }

        return response()->json([
            'status' => 'success',
            'title' => $title,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'domain_id' => $domainId,
            'domain_name' => $domainName,
            'family_id' => $familyId,
            'family_name' => $familyName,
            'products' => $catalogProducts,
        ]);
    }
}