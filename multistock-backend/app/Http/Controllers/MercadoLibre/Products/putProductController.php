<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class putProductoByUpdateController {

    public function putProductoByUpdate(Request $request, $clientId, $productId) {

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

        $userResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/me");

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener información del usuario.',
            ], 500);
        }

        $userId = $userResponse->json()['id'];

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
            Log::error("Error al actualizar producto en MercadoLibre", [
                'product_id' => $productId,
                'client_id' => $clientId,
                'payload_enviado' => $validatedData,
                'respuesta_ml' => $response->body(),
                'status_code' => $response->status(),
            ]);

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
