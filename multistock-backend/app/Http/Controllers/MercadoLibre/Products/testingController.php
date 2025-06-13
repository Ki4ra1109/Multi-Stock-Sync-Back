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
    public function testingGet(Request $request,$clientId){
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
        $userId = $userResponse->json()['id'];
        // Obtener el ID del usuario
        $validateData = $request->validate(['baseurl'=> 'sometimes | string']);
        $baseUrl='https://api.mercadolibre.com/users/{$userId}/items/search?catalog_listing=true';

        if(isset($validateData['baseurl'])) $baseUrl =$validateData['baseurl'];
        error_log($baseUrl);
        $response =Http::timeout(30)->withToken($credentials->access_token)->get($baseUrl);
        return response()->json($response->json(), $response->status());

    }
    public function testingPost(Request $request,$clientId){
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
        $userId = $userResponse->json()['id'];
        $body = [
            "domain_id" => "PANTIES",
            "site_id" => "MLC",
            "seller_id" => $userId,
            "attributes" => [
                [
                    "id" => "GENDER",
                    "values" => [
                        [
                            "name" => "Mujer"
                        ]
                    ]
                ],
                [
                    "id" => "BRAND",
                    "values" => [
                        [
                            "name" => "lady genny"
                        ]
                    ]
                ]
            ]
        ];
        $validateData = $request->validate(['baseurl'=> 'sometimes | string']);
        $baseUrl='https://api.mercadolibre.com/users/{$userId}/items/search?catalog_listing=true';

        if(isset($validateData['baseurl'])) $baseUrl =$validateData['baseurl'];
        error_log($baseUrl);
        $response =Http::timeout(30)->withToken($credentials->access_token)->post($baseUrl, $body);
        return response()->json($response->json(), $response->status());

    }
}
