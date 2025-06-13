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

    public function getSizeGuides(Request $request, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();
        error_log("credentials " . json_encode($credentials));
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token: ' . $e->getMessage(),
            ], 500);
        }

        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }

        error_log("userResponse " . json_encode($userResponse));
        $userId = $userResponse->json()['id'];
        $request->validate([
            'domain_id' => 'required|string',
            'gender' => 'required|string',
            'brand' => 'required|string',
        ]);

        $payload = [
            'domain_id' => explode('-', $request->domain_id)[1],
            'site_id' => explode('-', $request->domain_id)[0],
            'seller_id' => $userId,
            'attributes' => [
                [
                    'id' => 'GENDER',
                    'values' => [
                        ['name' => $request->gender]
                    ]
                ],
                [
                    'id' => 'BRAND',
                    'values' => [
                        ['name' => $request->brand]
                    ]
                ]
            ]
        ];

        error_log("payload " . json_encode($payload));
        $response = Http::withToken($credentials->access_token)->post('https://api.mercadolibre.com/catalog/charts/search', $payload);

        if ($response->failed()) {
            Log::error('Error al obtener guías de talles de Mercado Libre:', [
                'payload' => $payload,
                'ml_response' => $response->json()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener guías de talles',
                'ml_error' => $response->json(),
            ], $response->status());
        }

        // FILTRAR LA RESPUESTA AQUÍ
        $rawData = $response->json();
        $filteredData = $this->filterSizeGuideData($rawData);

        return response()->json([
            'status' => 'success',
            'total_guides'=>count($filteredData),
            'size_guides' => $filteredData

        ]);
    }

    /**
     * Filtra los datos de la guía de talles para obtener solo los campos necesarios
     */
    private function filterSizeGuideData($data)
    {
        $filtered = [];

        // Si tiene la estructura con 'charts' (estructura completa de MercadoLibre)
        if (isset($data['charts']) && is_array($data['charts'])) {
            foreach ($data['charts'] as $sizeGuide) {
                $filtered[] = $this->extractSizeGuideFields($sizeGuide);
            }
        } // Si $data es un array de guías de talles con 'results'
        elseif (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $sizeGuide) {
                $filtered[] = $this->extractSizeGuideFields($sizeGuide);
            }
        } // Si $data es una sola guía de talles
        elseif (isset($data['id'])) {
            $filtered = $this->extractSizeGuideFields($data);
        } // Si $data es un array directo de guías
        elseif (is_array($data)) {
            foreach ($data as $sizeGuide) {
                if (isset($sizeGuide['id'])) {
                    $filtered[] = $this->extractSizeGuideFields($sizeGuide);
                }
            }
        }

        return $filtered;
    }

    /**
     * Extrae los campos específicos de una guía de talles
     */
    private function extractSizeGuideFields($sizeGuide)
    {
        $result = [
            'id' => $sizeGuide['id'] ?? null,
            'names' => $sizeGuide['names'] ?? null,
            'main_attribute_id' => $sizeGuide['main_attribute_id'] ?? null,
            'rows' => []
        ];

        // Filtrar rows
        if (isset($sizeGuide['rows']) && is_array($sizeGuide['rows'])) {
            foreach ($sizeGuide['rows'] as $row) {
                $filteredRow = [
                    'id' => $row['id'] ?? null,
                    'attributes' => []
                ];

                // Filtrar attributes dentro de cada row
                if (isset($row['attributes']) && is_array($row['attributes'])) {
                    foreach ($row['attributes'] as $attribute) {
                        $filteredRow['attributes'][] = [
                            'id' => $attribute['id'] ?? null,
                            'values' => $attribute['values'] ?? []
                        ];
                    }
                }

                $result['rows'][] = $filteredRow;
            }
        }

        return $result;
    }
}
