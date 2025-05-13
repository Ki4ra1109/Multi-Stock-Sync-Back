<?php


namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class newSearch extends Controller
{
    public function newSearch(Request $request, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $userId = $response->json()['id'];
        $searchTerm = $request->query('q', '');

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/items/{$searchTerm}?include_attributes=all");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener los productos.',
                'error' => $response->json(),
            ], 500);
        }

        $products = $response->json();
        
        return response()->json([
            'status' => 'success',
            'products' => $products,
        ], 200);

    }
    
}    