<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class getSpecsDomainController extends Controller
{
    public function getSpecs(Request $request, $id)
    {
        $client_id = $request->query('client_id');

        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $client_id;
        $cred = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client_id) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $client_id");
            return \App\Models\MercadoLibreCredential::where('client_id', $client_id)->first();
        });

        if (!$cred || $cred->isTokenExpired()) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        $response = Http::withToken($cred->access_token)
            ->get("https://api.mercadolibre.com/domains/{$id}/technical_specs");

        return response()->json($response->json(), $response->status());
    }
}
