<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;

class getUpcomingShipmentsController
{
    public function getUpcomingShipments(Request $request, $clientId)
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
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }

        $userId = $userResponse->json()['id'];

        // Rango de fechas: hoy hasta dentro de 7 días
        $dateFrom = Carbon::now()->format('Y-m-d\T00:00:00.000-00:00');
        $dateTo = Carbon::now()->addDays(7)->format('Y-m-d\T23:59:59.999-00:00');

        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'order.status' => 'paid',
                'order.date_created.from' => $dateFrom,
                'order.date_created.to' => $dateTo,
                'limit' => 50
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $orders = $response->json()['results'];
        $upcomingOrders = [];

        foreach ($orders as $order) {
            $shippingId = $order['shipping']['id'] ?? null;

            if ($shippingId) {
                $shipmentResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/shipments/{$shippingId}");

                if ($shipmentResponse->successful()) {
                    $shipmentData = $shipmentResponse->json();

                    $dateReadyToShip = $shipmentData['date_ready_to_ship'] ?? null;

                    if ($dateReadyToShip) {
                        $fechaEnvio = Carbon::parse($dateReadyToShip);
                        $diasRestantes = Carbon::now()->diffInDays($fechaEnvio, false);

                        // --- AQUI: SOLO ENTRE 3 Y 7 DÍAS ---
                        if ($diasRestantes >= 3 && $diasRestantes <= 7) {
                            $upcomingOrders[] = [
                                'order_id' => $order['id'],
                                'shipping_id' => $shippingId,
                                'buyer' => $order['buyer']['nickname'] ?? '',
                                'items' => $order['order_items'],
                                'fecha_envio_programada' => $fechaEnvio->toDateTimeString(),
                                'dias_restantes' => $diasRestantes,
                                'shipment_status' => $shipmentData['status'] ?? null,
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Órdenes programadas entre 3 y 7 días obtenidas con éxito.',
            'data' => $upcomingOrders,
        ]);
    }
}
