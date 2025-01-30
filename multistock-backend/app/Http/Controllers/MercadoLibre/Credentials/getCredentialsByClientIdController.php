<?php

namespace App\Http\Controllers\MercadoLibre\Credentials;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getCredentialsByClientIdController
{

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

}