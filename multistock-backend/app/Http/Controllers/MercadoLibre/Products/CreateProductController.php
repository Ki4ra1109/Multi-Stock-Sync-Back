<?php
namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            'family_name' => 'required|string', // Ahora obligatorio si MercadoLibre lo exige
            'catalog_product_id' => 'nullable|string'
        ]);

        // Consultar si la categoría tiene catálogo obligatorio
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

        // Construir el payload para enviar a MercadoLibre
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
            'family_name' => $data['family_name'] // ✅ Se incluye en el payload
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
        }
        
        // Enviar producto a MercadoLibre
        $response = Http::withToken($credentials->access_token)
            ->post('https://api.mercadolibre.com/items', $data);

        if ($response->failed()) {
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