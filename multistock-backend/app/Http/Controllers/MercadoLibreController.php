<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MercadoLibreController extends Controller
{
    /**
     * Redirecto to MercadoLibre for authentication.
     */
    public function redirectToMercadoLibre()
    {
        $clientId = env('MERCADO_LIBRE_CLIENT_ID');
        $redirectUri = env('MERCADO_LIBRE_REDIRECT_URI');

        $authUrl = "https://auth.mercadolibre.cl/authorization?response_type=code"
                 . "&client_id={$clientId}"
                 . "&redirect_uri={$redirectUri}";

        return redirect($authUrl); // Redirect to MercadoLibre
    }

    /**
     * Handle callback after MercadoLibre authentication.
     */
    public function handleCallback(Request $request)
    {
        $code = $request->query('code'); // Get auth code

        if (!$code) {
            return response()->json(['error' => 'Authorization code not found'], 400);
        }

        // Change token for access token
        $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => env('MERCADO_LIBRE_CLIENT_ID'),
            'client_secret' => env('MERCADO_LIBRE_CLIENT_SECRET'),
            'code' => $code,
            'redirect_uri' => env('MERCADO_LIBRE_REDIRECT_URI'),
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to retrieve access token'], 500);
        }

        $data = $response->json();

        // Save code in database for future use
        return response()->json([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'],
        ]);
    }
}
