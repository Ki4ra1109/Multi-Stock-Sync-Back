<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MercadoLibreCredential;

class CategoriaController extends Controller
{
    public function getCategoria(Request $request, $id)
    {
        $client_id = $request->query('client_id');
        $cred = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$cred || $cred->isTokenExpired()) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        $response = Http::withToken($cred->access_token)
            ->get("https://api.mercadolibre.com/categories/%7B$id%7D");

        return response()->json($response->json(), $response->status());
    }

        public function getAtributos(Request $request, $id)
    {
        $client_id = $request->query('client_id');
        $cred = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$cred || $cred->isTokenExpired()) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        $response = Http::withToken($cred->access_token)
            ->get("https://api.mercadolibre.com/categories/%7B$id%7D/attributes");

        return response()->json($response->json(), $response->status());


    }

        public function getSpecs(Request $request, $id)
    {
        $client_id = $request->query('client_id');
        $cred = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$cred || $cred->isTokenExpired()) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        $response = Http::withToken($cred->access_token)
            ->get("https://api.mercadolibre.com/domains/%7B$id%7D/technical_specs");

        return response()->json($response->json(), $response->status());
    }
}