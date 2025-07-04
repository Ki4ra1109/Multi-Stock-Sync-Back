<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;


class getChinaCarrier extends Controller
{
    public function chinaProductsAllCompanies(Request $request)
    {
        set_time_limit(300); // 5 minutos

        // Validar parámetro
        if (!$request->has('company_id')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe enviar el parámetro company_id.'
            ], 400);
        }

        $company = Company::find($request->input('company_id'));
        if (!$company || !$company->client_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró la compañía o no tiene client_id asociado.'
            ], 404);
        }

        $credentials = MercadoLibreCredential::where('client_id', $company->client_id)->first();
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Refresca token si está expirado
        if ($credentials->isTokenExpired()) {
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials->client_id,
                'client_secret' => $credentials->client_secret,
                'refresh_token' => $credentials->refresh_token,
            ]);
            if ($refreshResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo refrescar el token',
                ], 401);
            }
            $data = $refreshResponse->json();
            $credentials->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }

        // Obtener productos activos publicados por la empresa
        $limit = intval($request->query('limit', 20));
        $offset = intval($request->query('offset', 0));
        $params = [
            'status' => 'active',
            'limit' => $limit,
            'offset' => $offset,
        ];

        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');
        $userId = $userResponse->json()['id'];

        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/' . $userId . '/items/search', $params);

        if ($response->failed()) {
            Log::error('Error consultando productos MLC', [
                'client_id' => $company->client_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar productos en Mercado Libre.',
            ]);
        }

        $itemIds = $response->json()['results'] ?? [];
        $internacionales = [];

        foreach ($itemIds as $itemId) {
            $itemResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$itemId}");
            if ($itemResponse->ok()) {
                $item = $itemResponse->json();
                $logistic = $item['shipping']['logistic_type'] ?? '';
                if (in_array($logistic, ['remote', 'cross_docking', 'xd_drop_off'])) {
                    $internacionales[] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'price' => $item['price'],
                        'date_created' => $item['date_created'],
                        'available_quantity' => $item['available_quantity'],
                        'condition' => $item['condition'],
                        'status' => $item['status'],
                        'pictures' => $item['pictures'],
                        'attributes' => $item['attributes'],
                        'permalink' => $item['permalink'],
                        'logistic_type' => $logistic,
                        'country' => $item['seller_address']['country']['id'] ?? '',
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Productos internacionales obtenidos correctamente.',
            'cantidad_total' => count($internacionales),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $internacionales,
        ]);
    }
}