<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;

class getDispatchEstimedLimitController
{
    public function getDispatchEstimedLimit($clientId)
    {
        set_time_limit(300);

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

        // Obtener user ID
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
        $from = Carbon::now()->subDays(6)->toIso8601String();
        $offset = 0;
        $limit = 50;
        $processedShipments = [];
        $shippingDetails = [];

        do {
            $response = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/orders/search", [
                    'seller' => $userId,
                    'order.status' => 'paid',
                    'order.date_created.from' => $from,
                    'order.date_created.to' => $to,
                    'sort' => 'date_desc',
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al conectar con la API de MercadoLibre.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $orders = $response->json()['results'] ?? [];

            foreach ($orders as $order) {
                $shippingId = $order['shipping']['id'] ?? null;

                if (!$shippingId || isset($processedShipments[$shippingId])) continue;

                $processedShipments[$shippingId] = true;

                $shipmentResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/shipments/{$shippingId}");

                if ($shipmentResponse->successful()) {
                    $shipmentData = $shipmentResponse->json();

                    $handlingLimitRaw = $shipmentData['shipping_option']['estimated_handling_limit']['date'] ?? null;

                    if ($handlingLimitRaw && $shipmentData['status_history']['date_shipped'] === null) {    
                        $handlingDate = Carbon::parse($handlingLimitRaw)->toDateString();
                        $today = Carbon::now()->toDateString();

                        if ($handlingDate === $today) {

                            $shippingDetails[] = [
                                'id' => $shipmentData['id'],
                                'estimated_handling_limit' => $handlingLimitRaw,
                                'shipping_date' => $shipmentData['status_history']['date_shipped'] ?? 'Aun no despachado',
                                'direction' => 
                                        ($shipmentData['receiver_address']['state']['name'] ?? '') . ' - ' .
                                        ($shipmentData['receiver_address']['city']['name'] ?? '') . ' - ' .
                                        ($shipmentData['receiver_address']['address_line'] ?? ''),

                                'receiver_name' => $shipmentData['receiver_address']['receiver_name'],
                                'order_id' => $order['id'],
                                'product' => $shipmentData['shipping_items'][0]['description'],
                                'quantity' => $shipmentData['shipping_items'][0]['quantity'],

                            ];
                        }
                    }
                }
            }

            $offset += $limit;
        } while (count($orders) === $limit);

        if (empty($shippingDetails)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron envíos con fecha límite de despacho para hoy.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Productos con fecha límite de despacho para hoy.',
            'total_envios' => count($shippingDetails),
            'data' => $shippingDetails,
        ]);
    }
}
