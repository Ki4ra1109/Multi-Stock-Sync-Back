<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class putProductoByUpdateController{
    
    public function putProductoByUpdate(Request $request ,$clientId, $productId){

        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/me");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener información del usuario.',
            ], 500);
        }

        $userId = $response->json()['id'];

        $validatedData = [];

        if ($request->has('available_quantity')) {
            $validatedData['available_quantity'] = $request->input('available_quantity');
        }
        if ($request->has('price')) {
            $validatedData['price'] = $request->input('price');
        }
        if ($request->has('pictures')) {
            $validatedData['pictures'] = $request->input('pictures');
        }
        if ($request->has('description')) {
            $validatedData['description'] = $request->input('description');
        }
        if ($request->has('shipping')) {
            $validatedData['shipping'] = $request->input('shipping');
        }
        if ($request->has('title')) {
            $validatedData['title'] = $request->input('title');
        }
        if ($request->has('listing_type_id')) {
            $validatedData['listing_type_id'] = $request->input('listing_type_id');
        }
        if ($request->has('status')) {
            $validatedData['status'] = $request->input('status');
        }
        if ($request->has('sale_terms')) {
            $validatedData['sale_terms'] = $request->input('sale_terms');
        }

        $response = Http::withToken($credentials->access_token)
            ->put("https://api.mercadolibre.com/items/{$productId}", $validatedData);

        if ($response->failed()) {  
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el producto en Mercado Libre.',
                'ml_error' => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Producto actualizado correctamente.',
            'data' => $response->json(),
        ], 200);
    }
}