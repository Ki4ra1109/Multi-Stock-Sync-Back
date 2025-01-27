<?php

namespace App\Http\Controllers;

use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MercadoLibreController extends Controller
{
    /**
     * Generate auth URL for MercadoLibre OAuth 2.0.
     */
    public function login(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        // Validate credentials by attempting to get an access token
        $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales inválidas. Por favor, verifique e intente nuevamente.',
            ], 400);
        }

        // Save or update credentials and token in the database
        $credentials = MercadoLibreCredential::updateOrCreate(
            [
                'client_id' => $request->input('client_id'),
            ],
            [
                'client_secret' => $request->input('client_secret'),
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
                'nickname' => null,
                'email' => null,
                'profile_image' => null,
            ]
        );

        // Generate a unique state
        $state = bin2hex(random_bytes(16));
        Cache::put("mercadolibre_state_{$state}", $credentials->id, now()->addMinutes(10));

        // Generate Auth URL
        $redirectUri = env('MERCADO_LIBRE_REDIRECT_URI');
        $authUrl = "https://auth.mercadolibre.cl/authorization?response_type=code"
                 . "&client_id={$credentials->client_id}"
                 . "&redirect_uri={$redirectUri}"
                 . "&state={$state}";

        return response()->json([
            'status' => 'success',
            'message' => 'Credenciales validadas. Redirigiendo a Mercado Libre...',
            'redirect_url' => $authUrl,
        ]);
    }

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

    /**
     * Get MercadoLibre credentials data.
    */

    public function getAllCredentialsData()
    {
        $credentials = MercadoLibreCredential::all();

        if ($credentials->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $credentials,
        ]);
    }

    /**
     * Get MercadoLibre credentials by client_id.
     */
    public function getCredentialsByClientId($client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales no encontradas.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $credentials,
        ]);
    }

    /**
     * Delete MercadoLibre credentials using app ID.
     */

    public function deleteCredentials($client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales no encontradas.',
            ], 404);
        }

        $credentials->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Credenciales eliminadas correctamente.',
        ]);
    }
}