<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class handleCallbackController
{

    /**
     * Handle callback from MercadoLibre.
     */
    public function handleCallback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');

        if (!$code || !$state) {
            return response()->json([
                'status' => 'error',
                'message' => 'Faltan parámetros necesarios en la respuesta del callback.',
            ], 400);
        }

        // Retrieve credentials ID from cache using the state
        $credentialId = Cache::pull("mercadolibre_state_{$state}");
        if (!$credentialId) {
            return response()->json([
                'status' => 'error',
                'message' => 'El state es inválido o expiró.',
            ], 400);
        }

        $credentials = MercadoLibreCredential::find($credentialId);
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales no encontradas.',
            ], 500);
        }

        // Exchange authorization code for tokens
        $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $credentials->client_id,
            'client_secret' => $credentials->client_secret,
            'code' => $code,
            'redirect_uri' => env('MERCADO_LIBRE_REDIRECT_URI'),
        ]);

        if ($response->failed()) {
            return response("<script>alert('Error al recuperar los tokens.'); window.close();</script>");
        }

        $data = $response->json();

        // Save tokens in the database
        $credentials->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        // Fetch additional user information using the access token
        $userInfoResponse = Http::withToken($data['access_token'])
            ->get('https://api.mercadolibre.com/users/me');

        if ($userInfoResponse->ok()) {
            $userInfo = $userInfoResponse->json();

            $credentials->update([
                'nickname' => $userInfo['nickname'] ?? null,
                'email' => $userInfo['email'] ?? null,
                'profile_image' => $userInfo['thumbnail']['picture_url'] ?? null,
            ]);
        }

        // Close the window with a success message
        return response("<script>alert('Autorización completada correctamente.'); window.close();</script>");
    }


    /**
     * Test connection with MercadoLibre API.
     */
    public function testConnection($credentialId)
    {
        $credentials = MercadoLibreCredential::find($credentialId);

        if (!$credentials || !$credentials->access_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró un token para estas credenciales. Por favor, inicie sesión primero.',
            ], 404);
        }

        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve la conexión.',
            ], 401);
        }

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