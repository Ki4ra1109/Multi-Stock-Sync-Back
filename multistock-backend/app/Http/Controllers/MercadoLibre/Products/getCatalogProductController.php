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

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        $title = $request->query('title');

        if (!$title) {
            return response()->json(['status' => 'error', 'message' => 'Falta el parámetro title'], 422);
        }

        // 1. Predicción de categoría
        $prediction = Http::get('https://api.mercadolibre.com/sites/MLC/domain_discovery/search', [
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
        $familyAvailable = false;

        // Atributos detectados como catálogo (opcional)
        $catalogAttributesDetected = !empty($data['attributes']);
        $suggestedAttributes = [];

        if ($catalogAttributesDetected) {
            foreach ($data['attributes'] as $attr) {
                $suggestedAttributes[] = [
                    'id' => $attr['id'],
                    'name' => $attr['name'],
                    'value_id' => $attr['value_id'] ?? null,
                    'value_name' => $attr['value_name'] ?? null
                ];
            }
        }

        // 2. Si hay family_id, buscar family_name real desde un producto
        if ($familyId) {
            $familyResponse = Http::get('https://api.mercadolibre.com/products/search', [
                'category_id' => $categoryId,
                'family_id' => $familyId,
                'limit' => 1
            ]);

            if ($familyResponse->ok() && !empty($familyResponse->json()['results'])) {
                $firstProductId = $familyResponse->json()['results'][0];
                $productDetail = Http::get("https://api.mercadolibre.com/products/{$firstProductId}");

                if ($productDetail->ok()) {
                    $familyName = $productDetail->json()['name'] ?? null;
                    $familyAvailable = true;
                }
            }
        }

        // ✅ Si family_name sigue siendo null, usamos el domain_name como alternativa
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
            'family_available' => $familyAvailable,
            'catalog_attributes_detected' => $catalogAttributesDetected,
            'suggested_attributes' => $suggestedAttributes
        ]);
    }
}