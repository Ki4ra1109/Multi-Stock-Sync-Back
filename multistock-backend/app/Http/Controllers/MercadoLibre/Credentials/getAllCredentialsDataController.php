<?php

namespace App\Http\Controllers\MercadoLibre\Credentials;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getAllCredentialsDataController
{

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

}