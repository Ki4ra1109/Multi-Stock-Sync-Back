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
            'family_name' => 'required|string', 
        ]);

        unset($data['family_name']);

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