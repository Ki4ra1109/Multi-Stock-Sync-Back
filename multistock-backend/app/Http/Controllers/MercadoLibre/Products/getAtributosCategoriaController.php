<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MercadoLibreCredential;

class getAtributosCategoriaController extends Controller
{
    public function getAtributos(Request $request, $id)
    {
        $client_id = $request->query('client_id');
        $cred = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$cred) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        if ($cred->isTokenExpired()) {
        $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $cred->client_id,
            'client_secret' => $cred->client_secret,
            'refresh_token' => $cred->refresh_token,
        ]);

        if ($refreshResponse->failed()) {
            return response()->json(['error' => 'No se pudo refrescar el token'], 401);
        }

        $data = $refreshResponse->json();
        $cred->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);
        }

        $response = Http::withToken($cred->access_token)
            ->get("https://api.mercadolibre.com/categories/{$id}/attributes");

        return response()->json($response->json(), $response->status());
    }
}
