<?php

use Illuminate\Support\Facades\Http;

class ReviewController extends Controller
{
    public function getReviews($productId)
    {
        // Ingresar el token de la conexión acá
        $accessToken = env('APP_USR-5822095179207900-022109-bc1711e2b00c06f2d5ffad0be732267d-1412503191');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get("https://api.mercadolibre.com/reviews/item/{$productId}");

        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json([
                'error' => 'No se pudieron obtener las opiniones del producto.',
            ], 500);
        }
    }
}
