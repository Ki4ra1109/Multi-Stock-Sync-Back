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

        $data = $request->validate([
            'title' => 'required|string',
            'category_id' => 'required|string',
            'price' => 'required|numeric',
            'currency_id' => 'required|string',
            'available_quantity' => 'required|integer',
            'condition' => 'required|in:new,used',
            'description' => 'required|string',
            'listing_type_id' => 'required|string',
            'pictures' => 'required|array',
            'pictures.*.source' => 'required|url',
            'sale_terms' => 'nullable|array',
            'shipping' => 'required|array',
            'attributes' => 'nullable|array',
            'family_name' => 'required|string',
            'catalog_product_id' => 'nullable|string'
        ]);

        // Verificar si la categoría requiere publicar en catálogo
        $catalogRequired = false;
        $categoryId = $data['category_id'];

        $attributeResponse = Http::get("https://api.mercadolibre.com/categories/{$categoryId}/attributes");

        if ($attributeResponse->successful()) {
            foreach ($attributeResponse->json() as $attr) {
                if (!empty($attr['tags']['catalog_required'])) {
                    $catalogRequired = true;
                    break;
                }
            }
        }

        // Validar que se incluya catalog_product_id si el catálogo es obligatorio
        if ($catalogRequired && empty($data['catalog_product_id'])) {
            // Intentar obtener un catalog_product_id de un producto similar
            $searchResponse = Http::get("https://api.mercadolibre.com/sites/MLC/search?q=" . urlencode($data['title']) . "&category=" . $categoryId);
            
            if ($searchResponse->successful()) {
                $products = $searchResponse->json()['results'];
                foreach ($products as $product) {
                    if (isset($product['catalog_product_id'])) {
                        $data['catalog_product_id'] = $product['catalog_product_id'];
                        break;
                    }
                }
            }

            if (empty($data['catalog_product_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Esta categoría requiere publicar en el catálogo. Debe incluir catalog_product_id.'
                ], 422);
            }
        }

        // Construir el payload para la publicación
        $payload = [
            'category_id' => $data['category_id'],
            'condition' => $data['condition'],
            'price' => $data['price'],
            'currency_id' => $data['currency_id'],
            'available_quantity' => $data['available_quantity'],
            'description' => [
                'plain_text' => $data['description']
            ],
            'listing_type_id' => $data['listing_type_id'],
            'pictures' => $data['pictures'],
            'shipping' => $data['shipping'],
            'family_name' => $data['family_name']
        ];

        if (empty($data['catalog_product_id']) && !empty($data['title'])) {
            $payload['title'] = $data['title'];
        }

        if (!empty($data['attributes'])) {
            $payload['attributes'] = $data['attributes'];
        }

        if (!empty($data['sale_terms'])) {
            $payload['sale_terms'] = $data['sale_terms'];
        }

        if (!empty($data['catalog_product_id']) && $data['catalog_product_id'] !== "undefined") {
            $payload['catalog_product_id'] = $data['catalog_product_id'];
            $payload['catalog_listing'] = true;
        }

        // Registrar el payload en logs para debug
        Log::info('Payload enviado a Mercado Libre:', $payload);

        // Enviar la publicación
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
