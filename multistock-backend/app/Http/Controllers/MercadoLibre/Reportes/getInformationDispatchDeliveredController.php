<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getInformationDispatchDeliveredController{

    public function getInformationDispatchDelivered($clientId, $deliveredId){

        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Por favor, valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $shippmentDetails = [];

        $shippmentResponse = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/shipments/$deliveredId");
        error_log($shippmentResponse->json());
        if ($shippmentResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la información del envío.',
                'error' =>  $shippmentResponse->json(),
            ], 500);
        }

        $shippmentDetails = [
            "receptor" => [
                        "receiver_id" => $shippmentResponse['receiver_id'],
                        "receiver_name" => $shippmentResponse['receiver_address']['receiver_name'],
                        "dirrection" =>
                            ($shippmentResponse['receiver_address']['state']['name'] ?? '') . ' - ' .
                            ($shippmentResponse['receiver_address']['city']['name'] ?? '') . ' - ' .
                            ($shippmentResponse['receiver_address']['address_line'] ?? ''),
                        ],
            "status_history" => $shippmentResponse['status_history'],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $shippmentDetails,
            "dataAll" => $shippmentResponse->json(),
        ]);
    }
}
