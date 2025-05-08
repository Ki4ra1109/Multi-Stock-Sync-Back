<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getStockCriticController
{
    public function getStockCritic($clienteId, $year = null, $month = null, $day = null)
    {
        set_time_limit(0); // Desactivar el límite de tiempo de ejecución

        $credentials = MercadoLibreCredential::where('client_id', $clienteId)->first();

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

        $userId = $response->json()['id'];
        $limit = 100;
        $offset = 0;
        $totalItems = 0;
        $productsStock = [];

        $baseUrl = "https://api.mercadolibre.com/users/{$userId}/items/search";
        $queryParams = [];

        if ($year) $queryParams['year'] = $year;
        if ($month) $queryParams['month'] = $month;
        if ($day) $queryParams['day'] = $day;

        if (!empty($queryParams)) {
            $baseUrl .= '?' . http_build_query($queryParams);
        }

        do {
            $response = Http::withToken($credentials->access_token)
                ->get($baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query([
                    'limit' => $limit,
                    'offset' => $offset,
                ]));

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al conectar con la API de MercadoLibre.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $json = $response->json();
            $items = $json['results'];
            $total = $json['paging']['total'];

            foreach ($items as $itemId) {
                $totalItems++;
                $itemResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/items/{$itemId}");

                if ($itemResponse->successful() && $itemResponse['available_quantity'] <= 5) {
                    $itemData = $itemResponse->json();

                    $productsStock[] = [
                        'id' => $itemData['id'],
                        'title' => $itemData['title'],
                        'available_quantity' => $itemData['available_quantity'],
                    ];
                }
            }

            
        } while ($offset < $total);

        return response()->json([
            'status' => 'success',
            'message' => 'Stock de productos obtenidos con éxito.',
            'products_count' => $totalItems,
            'data' => $productsStock,
        ]);
    }
}
