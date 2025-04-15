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

        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario. Valide su token.',
                'error' => $userResponse->json(),
            ], 500);
        }

        $userId = $userResponse->json()['id'];
        $to = Carbon::now()->toIso8601String();
        $from = Carbon::now()->subDays(30)->toIso8601String();

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'order.date_created.from' => $from,
                'order.date_created.to' => $to,
                'sort' => 'date_desc',
                'limit' => 50,
                'offset' => 0,
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'] ?? [];
        $processedShipments = [];
        $shippingDetails = [];

        foreach ($orders as $order) {
            $shippingId = $order['shipping']['id'] ?? null;

            if (!$shippingId || isset($processedShipments[$shippingId])) continue;

            $processedShipments[$shippingId] = true;

            $shipmentResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/shipments/{$shippingId}");

            if ($shipmentResponse->successful()) {
                $shipmentData = $shipmentResponse->json();

                // ✅ Filtrar solo los envíos entregados
                if ($shipmentData['status'] === 'delivered') {
                    $shippingDetails[] = $shipmentData;
                }
            }
        }

        if (empty($shippingDetails)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron envíos entregados disponibles para recepción.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Envíos entregados obtenidos con éxito.',
            'data' => $shippingDetails,
        ]);
    }
}
