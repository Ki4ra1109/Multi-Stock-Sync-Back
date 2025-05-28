<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class testingController
{
    public function testing(Request $request,$clientId){
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
        error_log("credentials " . json_encode($credentials));
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }
        try {
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
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
        // Comprobar si el token ha expirado y refrescarlo si es necesario


        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }
        error_log("userResponse " . json_encode($userResponse));
        // Obtener el ID del usuario
        $userId = $userResponse->json()['id'];
        $validated =
        $baseUrl='https://api.mercadolibre.com/categories/MLC6428/attributes/conditional';
        $response =Http::timeout(30)->withToken($credentials->access_token)->get($baseUrl);
        return response()->json($response->json(), $response->status());

    }
}
