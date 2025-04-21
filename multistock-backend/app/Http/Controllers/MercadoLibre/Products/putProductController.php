<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class putProductoByUpdateController{
    
    public function putProductoByUpdate($clientId, $productId){

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
            ->get("https://api.mercadolibre.com/items/$productId");
        
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el producto. Por favor, valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $product = $response->json();
        $oldQuantity = $product['available_quantity'];
        $quantity = request()->input('quantity', 0);
        $updateQuantity = $quantity + $oldQuantity;

        $updateResponse = Http::withToken($credentials->access_token)
            ->put("https://api.mercadolibre.com/items/$productId", [
                'available_quantity' => $updateQuantity,
            ]);
        
        if ($updateResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo actualizar el producto. Por favor, valide su token.',
                'error' => $updateResponse->json(),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Producto actualizado correctamente.',
            'data' => $updateResponse->json(),
        ], 200);
    }
}