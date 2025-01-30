<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class testAndRefreshConnectionController
{

    /**
     * Test and refresh MercadoLibre connection.
     */
    public function testAndRefreshConnection($client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales no encontradas.',
            ], 404);
        }

        if ($credentials->isTokenExpired()) {
            // Attempt to refresh the token
            $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials->client_id,
                'client_secret' => $credentials->client_secret,
                'refresh_token' => $credentials->refresh_token,
            ]);

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al refrescar el token. Por favor, inicie sesión nuevamente.',
                ], 401);
            }

            $data = $response->json();

            // Update tokens in the database
            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
                'updated_at' => now(), // Update the updated_at timestamp
            ]);
        } else {
            // Update the updated_at timestamp even if the token is not expired
            $credentials->touch();
        }

        // Test the connection with the refreshed or existing token
        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con MercadoLibre. El token podría ser inválido.',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Conexión exitosa.',
            'data' => $response->json(),
        ]);
    }

}