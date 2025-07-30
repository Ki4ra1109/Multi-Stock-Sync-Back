<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class getProductVariationsController extends Controller
{
    /**
     * Obtener las variaciones de un producto específico de Mercado Libre
     */
    public function getProductVariations(Request $request, $clientId, $itemId)
    {
        try {
            // Obtener credenciales del cliente
            $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

            if (!$credentials) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
                ], 404);
            }

            // Verificar si el token ha expirado
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);

                if ($refreshResponse->failed()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se pudo refrescar el token.',
                    ], 401);
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }

            // Hacer la petición a la API de Mercado Libre
            $response = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$itemId}/variations");

            if ($response->failed()) {
                Log::error('Error al obtener variaciones del producto', [
                    'item_id' => $itemId,
                    'client_id' => $clientId,
                    'response_status' => $response->status(),
                    'response_body' => $response->json()
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al obtener las variaciones del producto.',
                    'ml_error' => $response->json(),
                ], $response->status());
            }

            $variations = $response->json();

            // Filtrar y formatear la respuesta
            $formattedVariations = $this->formatVariations($variations);

            return response()->json([
                'status' => 'success',
                'message' => 'Variaciones obtenidas correctamente.',
                'item_id' => $itemId,
                'total_variations' => count($formattedVariations),
                'variations' => $formattedVariations
            ]);

        } catch (\Exception $e) {
            Log::error('Error general al obtener variaciones', [
                'item_id' => $itemId,
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las variaciones del producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una variación específica de un producto
     */
    public function getSpecificVariation(Request $request, $clientId, $itemId, $variationId)
    {
        try {
            // Obtener credenciales del cliente
            $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

            if (!$credentials) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
                ], 404);
            }

            // Verificar si el token ha expirado
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);

                if ($refreshResponse->failed()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se pudo refrescar el token.',
                    ], 401);
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }

            // Hacer la petición a la API de Mercado Libre
            $response = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$itemId}/variations/{$variationId}");

            if ($response->failed()) {
                Log::error('Error al obtener variación específica', [
                    'item_id' => $itemId,
                    'variation_id' => $variationId,
                    'client_id' => $clientId,
                    'response_status' => $response->status(),
                    'response_body' => $response->json()
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al obtener la variación del producto.',
                    'ml_error' => $response->json(),
                ], $response->status());
            }

            $variation = $response->json();

            return response()->json([
                'status' => 'success',
                'message' => 'Variación obtenida correctamente.',
                'item_id' => $itemId,
                'variation_id' => $variationId,
                'variation' => $this->formatSingleVariation($variation)
            ]);

        } catch (\Exception $e) {
            Log::error('Error general al obtener variación específica', [
                'item_id' => $itemId,
                'variation_id' => $variationId,
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la variación del producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatear la lista de variaciones
     */
    private function formatVariations($variations)
    {
        $formatted = [];

        foreach ($variations as $variation) {
            $formatted[] = $this->formatSingleVariation($variation);
        }

        return $formatted;
    }

    /**
     * Formatear una variación individual
     */
    private function formatSingleVariation($variation)
    {
        return [
            'id' => $variation['id'] ?? null,
            'price' => $variation['price'] ?? null,
            'attribute_combinations' => $variation['attribute_combinations'] ?? [],
            'available_quantity' => $variation['available_quantity'] ?? 0,
            'sold_quantity' => $variation['sold_quantity'] ?? 0,
            'sale_terms' => $variation['sale_terms'] ?? [],
            'picture_ids' => $variation['picture_ids'] ?? [],
            'catalog_product_id' => $variation['catalog_product_id'] ?? null,
            'attribute_combinations_formatted' => $this->formatAttributeCombinations($variation['attribute_combinations'] ?? [])
        ];
    }

    /**
     * Formatear las combinaciones de atributos de forma legible
     */
    private function formatAttributeCombinations($attributeCombinations)
    {
        $formatted = [];

        foreach ($attributeCombinations as $combination) {
            $formatted[] = [
                'id' => $combination['id'] ?? null,
                'name' => $combination['name'] ?? null,
                'value_id' => $combination['value_id'] ?? null,
                'value_name' => $combination['value_name'] ?? null,
                'display_name' => ($combination['name'] ?? '') . ': ' . ($combination['value_name'] ?? '')
            ];
        }

        return $formatted;
    }
} 