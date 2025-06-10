<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateProductController extends Controller
{
    public function create(Request $request, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        $data = $request->all();
        $hasCatalog = !empty($data['catalog_product_id']);

        $rules = [
            'catalog_product_id' => 'nullable|string',
            'price' => 'required|numeric',
            'currency_id' => 'required|string',
            'available_quantity' => 'required|integer',
            'condition' => 'required|in:new,used',
            'listing_type_id' => 'required|string',
            'pictures' => 'required|array',
            'pictures.*.source' => 'required|url',
            'shipping' => 'required|array',
            'sale_terms' => 'nullable|array',
            'attributes' => 'nullable|array',
            'SIZE_GRID_ID' => 'nullable|string',
            // Nuevas reglas para variaciones
            'variations' => 'nullable|array',
            'variations.*.attribute_combinations' => 'required_with:variations|array',
            'variations.*.attribute_combinations.*.id' => 'required_with:variations|string',
            'variations.*.attribute_combinations.*.value_name' => 'required_with:variations|string',
            'variations.*.attributes' => 'nullable|array',
            'variations.*.attributes.*.id' => 'required_with:variations.*.attributes|string',
            'variations.*.attributes.*.value_name' => 'required_with:variations.*.attributes|string',
            'variations.*.price' => 'nullable|numeric',
            'variations.*.available_quantity' => 'nullable|integer',
            'variations.*.picture_ids' => 'nullable|array',
            'variations.*.seller_custom_field' => 'nullable|string',
        ];

        if (!$hasCatalog) {
            $rules['title'] = 'required|string';
            $rules['category_id'] = 'required|string';
            $rules['description'] = 'nullable|string';

            // Verificar si category_id existe antes de usarlo
            if (!empty($data['category_id'])) {
                $categoryAttributes = Http::get("https://api.mercadolibre.com/categories/{$data['category_id']}/attributes")->json();
                if (collect($categoryAttributes)->contains('id', 'FAMILY_NAME')) {
                    $rules['family_name'] = 'required|string';
                }
            }
        }

        $validated = validator($data, $rules)->validate();

        // Verificación si la categoría requiere publicación por catálogo
        $catalogRequired = false;
        if (!$hasCatalog && !empty($validated['category_id'])) {
            $attributeResponse = Http::get("https://api.mercadolibre.com/categories/{$validated['category_id']}/attributes");

            if ($attributeResponse->successful()) {
                foreach ($attributeResponse->json() as $attr) {
                    if (!empty($attr['tags']['catalog_required'])) {
                        $catalogRequired = true;
                        break;
                    }
                }
            }

            if ($catalogRequired) {
                $searchCatalog = Http::get("https://api.mercadolibre.com/products/search", [
                    'category' => $validated['category_id'],
                    'q' => $validated['title'] ?? ''
                ]);

                if ($searchCatalog->successful() && !empty($searchCatalog['results'])) {
                    $validated['catalog_product_id'] = $searchCatalog['results'][0]['id'];
                    $hasCatalog = true;
                }

                if (empty($validated['catalog_product_id'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Esta categoría requiere publicar en el catálogo y no se encontró un catalog_product_id válido.'
                    ], 422);
                }
            }
        }

        $payload = [
            'price' => $validated['price'],
            'currency_id' => $validated['currency_id'],
            'available_quantity' => $validated['available_quantity'],
            'listing_type_id' => $validated['listing_type_id'],
            'condition' => $validated['condition'],
            'pictures' => $validated['pictures'],
            'shipping' => $validated['shipping'],
        ];

        if ($hasCatalog && !empty($validated['catalog_product_id']) && $validated['catalog_product_id'] !== 'undefined') {
            $payload['catalog_product_id'] = $validated['catalog_product_id'];
            $payload['catalog_listing'] = true;

            unset($payload['title'], $payload['description'], $payload['family_name']);
        } else {
            $payload['title'] = $validated['title'];
            $payload['category_id'] = $validated['category_id'];

            if (!empty($validated['description'])) {
                $payload['description'] = ['plain_text' => $validated['description']];
            }

            if (!$hasCatalog && !empty($validated['family_name'])) {
                $payload['family_name'] = $validated['family_name'];
            }


            if (!empty($validated['attributes'])) {
                $payload['attributes'] = $validated['attributes'];
            }
        }

        if (!empty($validated['sale_terms'])) {
            $payload['sale_terms'] = $validated['sale_terms'];
        }


        if (!empty($validated['variations'])) {
            $payload['variations'] = $this->processVariations($validated['variations']);
        }

        if (!empty($validated['category_id'])) {
            $payload['category_id'] = $validated['category_id'];
        }

        Log::info('Payload enviado a Mercado Libre:', $payload);

        $response = Http::withToken($credentials->access_token)
            ->post('https://api.mercadolibre.com/items', $payload);

        if ($response->failed()) {
            Log::error('Error al crear el producto en Mercado Libre:', [
                'payload' => $payload,
                'ml_response' => $response->json()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el producto',
                'ml_error' => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Producto creado en Mercado Libre con éxito',
            'ml_response' => $response->json()
        ]);
    }

    /**
     * Procesa las variaciones para el formato correcto de MercadoLibre
     */
    private function processVariations(array $variations): array
    {
        $processedVariations = [];

        foreach ($variations as $variation) {
            $processedVariation = [
                'attribute_combinations' => $variation['attribute_combinations']
            ];

            // Agregar attributes si existen (como SIZE_GRID_ROW_ID)
            if (!empty($variation['attributes'])) {
                $processedVariation['attributes'] = $variation['attributes'];
            }

            // Agregar precio específico si existe
            if (isset($variation['price'])) {
                $processedVariation['price'] = $variation['price'];
            }

            // Agregar cantidad disponible específica si existe
            if (isset($variation['available_quantity'])) {
                $processedVariation['available_quantity'] = $variation['available_quantity'];
            }

            // Agregar IDs de imágenes específicas si existen
            if (!empty($variation['picture_ids'])) {
                $processedVariation['picture_ids'] = $variation['picture_ids'];
            }

            // Agregar campo personalizado del vendedor si existe
            if (!empty($variation['seller_custom_field'])) {
                $processedVariation['seller_custom_field'] = $variation['seller_custom_field'];
            }

            $processedVariations[] = $processedVariation;
        }

        return $processedVariations;
    }


}
