<?php

namespace App\Services\MercadoLibre;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLibreCredentialService
{
    public static function getValidCredentials(string $client_id): ?MercadoLibreCredential
    {
        $cacheKey = 'ml_credentials_' . $client_id;

        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client_id) {
            Log::info("🔐 Consultando credenciales para client_id: $client_id");
            return MercadoLibreCredential::where('client_id', $client_id)->first();
        });

        if (!$credentials || !$credentials->access_token) {
            return null;
        }

        try {
            $responseCheck = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');
            if ($responseCheck->status() === 401 || method_exists($credentials, 'isTokenExpired') && $credentials->isTokenExpired()) {
                Log::info("♻️ Token inválido o expirado, refrescando para client_id: $client_id");
                return self::refreshToken($client_id);
            }
        } catch (\Throwable $e) {
            Log::error("❌ Error al verificar validez del token para $client_id", [
                'error' => $e->getMessage()
            ]);
            return self::refreshToken($client_id);
        }

        return $credentials;
    }

    public static function refreshToken(string $client_id): ?MercadoLibreCredential
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();
        if (!$credentials) return null;

        $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $credentials->client_id,
            'client_secret' => $credentials->client_secret,
            'refresh_token' => $credentials->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error("❌ No se pudo refrescar el token para $client_id", ['response' => $response->body()]);
            return null;
        }

        $data = $response->json();
        $credentials->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        $cacheKey = 'ml_credentials_' . $client_id;
        Cache::forget($cacheKey);
        Cache::put($cacheKey, $credentials, now()->addMinutes(10));

        return $credentials;
    }
}

// en resumen nos sirve para obtener las credenciales válidas de Mercado Libre, refrescar el token si es necesario y manejar la caché para mejorar el rendimiento.
// También maneja errores y logs para facilitar el diagnóstico de problemas con las credenciales.