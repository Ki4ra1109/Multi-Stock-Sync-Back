<?php

namespace App\Http\Controllers\MercadoLibre\Login;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class loginController
{

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

}