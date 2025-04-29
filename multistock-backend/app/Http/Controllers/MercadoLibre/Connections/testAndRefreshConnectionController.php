<?php

namespace App\Http\Controllers\MercadoLibre\Connections;

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

        // Intentar conexión inmediata
        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            // Si falla, intentamos refrescar
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials->client_id,
                'client_secret' => $credentials->client_secret,
                'refresh_token' => $credentials->refresh_token,
            ]);

            if ($refreshResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al refrescar el token. Por favor, inicie sesión nuevamente.',
                ], 401);
            }

            $data = $refreshResponse->json();

            // Actualizar la base de datos con los nuevos tokens
            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
                'updated_at' => now(),
            ]);

            // Reintentar la conexión con el nuevo token
            $response = Http::withToken($data['access_token'])
                ->get('https://api.mercadolibre.com/users/me');

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al conectar después de refrescar. Token posiblemente inválido.',
                ], 500);
            }
        } else {
            // Si todo está bien desde el principio, solo actualizamos el updated_at
            $credentials->touch();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Conexión exitosa.',
            'data' => $response->json(),
        ]);
    }
}
