<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GetDispatchHistoryController extends Controller
{
    /**
     * Obtener el historial de despacho de un producto con paginación.
     */
    public function getDispatchHistory(Request $request, $clientId, $productId)
    {
        // Obtener credenciales del cliente
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales para el client_id proporcionado.',
            ], 404);
        }

        // Verificar si el token ha expirado
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        // Obtener el ID del usuario autenticado en MercadoLibre
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], $userResponse->status());
        }

        $userId = $userResponse->json()['id'];

        // Parámetros de paginación
        $limit = $request->query('limit', 50); // Número de registros por página (por defecto 50)
        $offset = $request->query('offset', 0); // Desde qué registro empezar

        // Llamada a la API de MercadoLibre con paginación
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $userId,
                'q' => $productId,
                'offset' => $offset,
                'limit' => $limit,
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        $data = $response->json();
        $orders = $data['results'] ?? [];

        // Procesar los pedidos para obtener la información de envío
        $dispatchHistory = [];

        foreach ($orders as $order) {
            if (!isset($order['shipping']['id'])) {
                continue; // Si no hay información de envío, saltamos este pedido
            }

            // Obtener detalles del envío
            $shippingId = $order['shipping']['id'];
            $shippingResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/shipments/{$shippingId}");

            if ($shippingResponse->failed()) {
                continue; // Si falla la consulta de envío, pasamos al siguiente pedido
            }

            $shippingData = $shippingResponse->json();

            // Filtrar solo aquellos con estado "shipped"
            if ($shippingData['status'] !== 'shipped') {
                continue; // Si no está en estado "shipped", lo omitimos
            }

            // Acceder al campo date_shipped desde status_history
            $dateShipped = isset($shippingData['status_history']['date_shipped'])
                ? date('Y-m-d H:i:s', strtotime($shippingData['status_history']['date_shipped']))
                : 'No disponible';

            // Colocar los detalles de envío en el array
            $dispatchHistory[] = [
                'shipping_id' => $shippingData['id'] ?? 'No disponible',
                'status' => $shippingData['status'] ?? 'Desconocido',
                'tracking_number' => $shippingData['tracking_number'] ?? 'No disponible',
                'date_shipped' => $dateShipped, // Aquí estamos colocando la fecha de envío
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Historial de despacho obtenido con éxito.',
            'data' => [
                'total' => $data['paging']['total'] ?? 0,
                'limit' => $data['paging']['limit'] ?? $limit,
                'offset' => $data['paging']['offset'] ?? $offset,
                'dispatch_history' => $dispatchHistory,
            ],
        ]);
    }
}
