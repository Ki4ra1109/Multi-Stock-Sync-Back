<?php

namespace App\Http\Controllers;

use App\Models\MercadoLibreCredential;
use App\Models\MercadoLibreToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MercadoLibreController extends Controller
{
    /**
     * Generate auth URL (MercadoLibre)
     */
    public function login(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        // Validate credentials with Mercado Libre API
        $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID de Cliente o Secreto de Cliente inválido. Por favor, verifique sus credenciales.',
                'error' => $response->json(),
            ], 400);
        }

        // Save credentials in database
        MercadoLibreCredential::updateOrCreate(
            ['id' => 1],
            [
                'client_id' => $request->input('client_id'),
                'client_secret' => $request->input('client_secret'),
            ]
        );

        // Generate Auth URL
        $redirectUri = env('MERCADO_LIBRE_REDIRECT_URI');
        $authUrl = "https://auth.mercadolibre.cl/authorization?response_type=code"
                 . "&client_id={$request->input('client_id')}"
                 . "&redirect_uri={$redirectUri}";

        return response()->json([
            'status' => 'success',
            'message' => 'Credenciales validadas. Redirigiendo a Mercado Libre...',
            'redirect_url' => $authUrl,
        ]);
    }

    /**
     * Function to save credentials in database
     */

    public function saveCredentials(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        // Save credentials in database
        MercadoLibreCredential::updateOrCreate(
            ['id' => 1],
            [
                'client_id' => $request->input('client_id'),
                'client_secret' => $request->input('client_secret'),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Credenciales del cliente guardadas con éxito.',
        ]);
    }

    /**
     * Handle callback from Mercado Libre
     */
    public function handleCallback(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código de autorización no encontrado.',
            ], 400);
        }

        $credentials = MercadoLibreCredential::find(1);

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales de cliente no encontradas. Por favor, inicie sesión nuevamente.',
            ], 500);
        }

        // Change code for token
        $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $credentials->client_id,
            'client_secret' => $credentials->client_secret,
            'code' => $code,
            'redirect_uri' => env('MERCADO_LIBRE_REDIRECT_URI'),
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al recuperar los tokens.',
                'error' => $response->json(),
            ], 500);
        }

        $data = $response->json();

        // Save tokens in database
        MercadoLibreToken::updateOrCreate(
            ['id' => 1],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Tokens almacenados con éxito.',
        ]);
    }

    /**
     * Test connection with Mercado Libre
     */
    public function testConnection()
    {
        $token = MercadoLibreToken::find(1);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró token. Por favor, inicie sesión primero.',
            ], 404);
        }

        // Validate token 
        $response = Http::withToken($token->access_token)
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
     * Logout and delete all credentials and tokens
     */
    public function logout()
    {
        MercadoLibreCredential::truncate();
        MercadoLibreToken::truncate();

        return response()->json([
            'status' => 'success',
            'message' => 'Cierre de sesión y eliminación de todas las credenciales con éxito.',
        ]);
    }
}
