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
        set_time_limit(300);
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        if ($credentials->isTokenExpired()) {
        $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $credentials->client_id,
            'client_secret' => $credentials->client_secret,
            'refresh_token' => $credentials->refresh_token,
        ]);

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
        $from = Carbon::now()->subDays(2)->toIso8601String();
        $offset = 0;
        $limit = 50;
        $processedShipments = [];
        $shippingDetails = [];

        do{
            // Obtener órdenes de los últimos 30 días
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
        
                foreach ($orders as $order) {
                    $shippingId = $order['shipping']['id'] ?? null;
        
                    if (!$shippingId || isset($processedShipments[$shippingId])) continue;
        
                    $processedShipments[$shippingId] = true;
        
                    $shipmentResponse = Http::withToken($credentials->access_token)
                        ->get("https://api.mercadolibre.com/shipments/{$shippingId}");
        
                    if ($shipmentResponse->successful()) {
                        $shipmentData = $shipmentResponse->json();
        
                        // Filtrar solo los envíos entregados
                        if ($shipmentData['status'] === 'shipped' || $shipmentData['status'] === 'delivered' && $shipmentData['substatus'] !== null) { 
                            $shippingDetails[] = [
                                "shipping_id" => $shipmentData['id'],
                                "productId" => $order['order_items'][0]['item']['id'],
                                "title" => $shipmentData['shipping_items'][0]['description'] ?? null,
                                "quantity" => $shipmentData['shipping_items'][0]['quantity'] ?? null,
                                "size" => $shipmentData['shipping_items'][0]['dimensions'] ?? null,
                                "substatus_history" => $shipmentData['substatus_history'] ?? null,
                                "tracking_number" => $shipmentData['tracking_number'] ?? null,
                                "receptor" => [
                                    "receiver_id" => $shipmentData['receiver_id'],
                                    "receiver_name" => $shipmentData['receiver_address']['receiver_name'],
                                    "dirrection" => 
                                        ($shipmentData['receiver_address']['state']['name'] ?? '') . ' - ' .
                                        ($shipmentData['receiver_address']['city']['name'] ?? '') . ' - ' .
                                        ($shipmentData['receiver_address']['address_line'] ?? ''),
                                ],
                                "substatus" => $shipmentData['substatus'],
                            ];
                        }
                    }
                }
            $offset += $limit;
        }while(count($orders) === $limit);

        if (empty($shippingDetails)) {
            return response()->json([
                'status' => 'No hay envios en transito',
                'message' => $shippingDetails,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Envíos en transito obtenidos con éxito.',
            "total envios" => count($shippingDetails),
            'data' => $shippingDetails,
        ]);
    }
}
