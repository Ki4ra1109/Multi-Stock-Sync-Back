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

        // Validar datos flexiblemente según si se incluye catalog_product_id
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
            'sale_terms' => 'nullable|array'
        ];

        // Solo se requieren si no se publica por catálogo
        if (!$hasCatalog) {
            $rules['title'] = 'required|string';
            $rules['category_id'] = 'required|string';
            $rules['description'] = 'nullable|string';
            $rules['family_name'] = 'required|string';
            $rules['attributes'] = 'nullable|array';
        }

        $validated = validator($data, $rules)->validate();

        // Si no viene catalog_product_id, intentar buscarlo si la categoría lo requiere
        $catalogRequired = false;
        if (!$hasCatalog && isset($validated['category_id'])) {
            $attributeResponse = Http::get("https://api.mercadolibre.com/categories/{$validated['category_id']}/attributes");

            if ($attributeResponse->successful()) {
                foreach ($attributeResponse->json() as $attr) {
                    if (!empty($attr['tags']['catalog_required'])) {
                        $catalogRequired = true;
                        break;
                    }
                }
            }

            // Intentar buscar el catalog_product_id si es requerido
            if ($catalogRequired) {
                $searchCatalog = Http::get("https://api.mercadolibre.com/products/search", [
                    'category' => $validated['category_id'],
                    'q' => $validated['title']
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

        // Construcción del payload
        $payload = [
            'price' => $validated['price'],
            'currency_id' => $validated['currency_id'],
            'available_quantity' => $validated['available_quantity'],
            'listing_type_id' => $validated['listing_type_id'],
            'condition' => $validated['condition'],
            'pictures' => $validated['pictures'],
            'shipping' => $validated['shipping']
        ];

        // Publicación con catálogo
        if ($hasCatalog && !empty($validated['catalog_product_id']) && $validated['catalog_product_id'] !== 'undefined') {
            $payload['catalog_product_id'] = $validated['catalog_product_id'];
            $payload['catalog_listing'] = true;
        } else {
            // Publicación manual
            $payload['title'] = $validated['title'];
            $payload['category_id'] = $validated['category_id'];

            if (!empty($validated['description'])) {
                $payload['description'] = ['plain_text' => $validated['description']];
            }

            $payload['family_name'] = $validated['family_name'];

            if (!empty($validated['attributes'])) {
                $payload['attributes'] = $validated['attributes'];
            }
        }

        // Agregar sale_terms si aplica
        if (!empty($validated['sale_terms'])) {
            $payload['sale_terms'] = $validated['sale_terms'];
        }

        // ✅ Agregar category_id si viene, incluso con catálogo
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
}
