<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class deleteCredencialsController
{

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