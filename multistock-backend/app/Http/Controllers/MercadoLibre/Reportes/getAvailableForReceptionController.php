<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;

class getAvailableForReceptionController
{
    public function getAvailableForReception($clientId)
    {
        
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
                'message' => 'No se pudo obtener el ID del usuario. Valide su token.',
                'error' => $response->json(),
            ], 500);
        }

        $userId = $response->json()['id'];

        
        $to = Carbon::now()->toIso8601String(); // Fecha y hora actual
        $from = Carbon::now()->subDays(30)->toIso8601String(); // Fecha y hora 30 días atrás
        $allSales = [];

        $response = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/orders/search", [
                    'seller' => $userId,
                    'order.status' => 'paid',
                    'order.date_created.from' => $from,
                    'order.date_created.to' => $to,
                    'sort' => 'date_desc',
                ]);
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }
        
        $allSales[] = $response->json()['results'];
        $shippingDetails = [];
        
        foreach($allSales as $orders){
            foreach($orders as $order){
                $shippingId = $order['shipping']['id'] ?? null;
        
                if (!$shippingId || isset($processedShipments[$shippingId])) continue;
        
                $processedShipments[$shippingId] = true;
        
                $response = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/shipments/{$shippingId}");
        
                if ($response['status'] === "shipped") {
                    $shippingDetails[] = $response->json();
                }
            }
        }
        
        if (empty($shippingDetails)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron envíos pendientes de recepción.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Envíos pendientes de recepción obtenidos con éxito.',
            'data' => $shippingDetails,
        ]);
    }
}
