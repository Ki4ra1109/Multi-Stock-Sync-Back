<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class getCategoriaController extends Controller
{
    public function getCategoria(Request $request, $id)
    {
        $client_id = $request->query('client_id');

        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $client_id;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client_id) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $client_id");
            return \App\Models\MercadoLibreCredential::where('client_id', $client_id)->first();
        });

        error_log("credentials " . json_encode($credentials));
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        try {
            if ($credentials->isTokenExpired()) {
                $refreshResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);
                // Si la solicitud falla, devolver un mensaje de error
                if ($refreshResponse->failed()) {
                    return response()->json(['error' => 'No se pudo refrescar el token'], 401);
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token: ' . $e->getMessage(),
            ], 500);
        }

        $response = \Illuminate\Support\Facades\Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/categories/{$id}");

        return response()->json($response->json(), $response->status());
    }
}
