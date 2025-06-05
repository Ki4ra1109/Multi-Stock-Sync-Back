<?php
namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class getCompaniesProductsController extends Controller
{
    public function getTotalSalesAllCompanies(Request $request)
    {
        $clientIds = Company::whereNotNull('client_id')->pluck('client_id')->toArray();

        // Obtener año y mes desde la query
        
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('m'));

        // Calcular el rango de 3 meses
        $start = \Carbon\Carbon::create($year, $month, 1)->subMonth()->startOfMonth();
        $end = \Carbon\Carbon::create($year, $month, 1)->addMonth()->endOfMonth();

        $dateFrom = $start->toIso8601String();
        $dateTo = $end->toIso8601String();

        $allSales = [];
        $totalSales = 0;
        $client = new Client(['timeout' => 20]);
        $promises = [];

        foreach ($clientIds as $clientId) {
            Log::info("Procesando empresa", ['client_id' => $clientId]);

            $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
            if (!$credentials) {
                Log::warning("No credentials found for client_id: $clientId");
                continue;
            }

            // Refresh token
            if ($credentials->isTokenExpired()) {
                Log::info("Token expirado, refrescando para client_id: $clientId");
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => env('MELI_CLIENT_ID'),
                    'client_secret' => env('MELI_CLIENT_SECRET'),
                    'refresh_token' => $credentials->refresh_token,
                ]);
                if ($refreshResponse->failed()) {
                    Log::error("Token refresh failed for client_id: $clientId", ['response' => $refreshResponse->json()]);
                    continue;
                }
                $newTokenData = $refreshResponse->json();
                $credentials->access_token = $newTokenData['access_token'];
                $credentials->refresh_token = $newTokenData['refresh_token'] ?? $credentials->refresh_token;
                $credentials->expires_in = $newTokenData['expires_in'];
                $credentials->updated_at = now();
                $credentials->save();
                Log::info("Token refrescado correctamente para client_id: $clientId");
            }

            $userResponse = Http::withToken($credentials->access_token)->get('https://api.mercadolibre.com/users/me');
            if ($userResponse->failed()) {
                Log::error("Failed to get user ID for client_id: $clientId", ['response' => $userResponse->json()]);
                continue;
            }
            $userId = $userResponse->json()['id'];
            Log::info("Obtenido user_id para client_id: $clientId", ['user_id' => $userId]);

            $limit = 20;
            $maxPages = 50;
            $pagePromises = [];
            for ($page = 0; $page < $maxPages; $page++) {
                $params = [
                    'seller' => $userId,
                    'order.status' => 'paid',
                    'order.date_created.from' => $dateFrom,
                    'order.date_created.to' => $dateTo,
                    'limit' => $limit,
                    'offset' => $page * $limit
                ];
                $pagePromises[] = $client->getAsync('https://api.mercadolibre.com/orders/search', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $credentials->access_token,
                    ],
                    'query' => $params
                ]);
            }
            $promises[$clientId] = Promise\Utils::all($pagePromises);
        }

        $results = Promise\Utils::settle($promises)->wait();

        // Procesamiento de resultados
        Log::info("Procesando resultados de órdenes pagadas");
        $salesByCompany = [];
        foreach ($results as $clientId => $result) {
            if ($result['state'] === 'fulfilled' && is_array($result['value'])) {
                foreach ($result['value'] as $response) {
                    if ($response->getStatusCode() === 200) {
                        $data = json_decode($response->getBody()->getContents(), true);
                        if (isset($data['results']) && is_array($data['results'])) {
                            foreach ($data['results'] as $order) {
                                if (!isset($order['order_items']) || !is_array($order['order_items'])) continue;
                                $orderMonth = \Carbon\Carbon::parse($order['date_created'])->format('Y-m');
                                if (!isset($salesByCompany[$clientId][$orderMonth])) {
                                    $salesByCompany[$clientId][$orderMonth] = [
                                        'total_sales' => 0,
                                        'orders' => []
                                    ];
                                }
                                if (isset($order['total_amount'])) {
                                    $salesByCompany[$clientId][$orderMonth]['total_sales'] += $order['total_amount'];
                                    $totalSales += $order['total_amount'];
                                }
                                $orderData = [
                                    'id' => $order['id'],
                                    'created_date' => $order['date_created'] ?? null,
                                    'total_amount' => $order['total_amount'] ?? null,
                                    'status' => $order['status'] ?? null,
                                    'products' => []
                                ];
                                foreach ($order['order_items'] as $item) {
                                    $orderData['products'][] = [
                                        'title' => $item['item']['title'] ?? null,
                                        'quantity' => $item['quantity'] ?? null,
                                        'price' => $item['unit_price'] ?? null
                                    ];
                                }
                                $salesByCompany[$clientId][$orderMonth]['orders'][] = $orderData;
                            }
                        }
                    }
                }
            }
        }

        // Solo mostrar los 3 meses
        $monthsToShow = [];
        for ($i = -1; $i <= 1; $i++) {
            $targetMonth = \Carbon\Carbon::create($year, $month, 1)->copy()->addMonths($i)->format('Y-m');
            $monthsToShow[] = $targetMonth;
        }
        foreach ($salesByCompany as $clientId => &$months) {
            $months = array_filter(
                $months,
                fn($v, $k) => in_array($k, $monthsToShow),
                ARRAY_FILTER_USE_BOTH
            );
            ksort($months);
        }
        unset($months);

        foreach ($allSales as $clientId => $clientOrders) {
            foreach ($clientOrders as $order) {
                
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Órdenes pagadas agrupadas por empresa y mes.',
            'sales_by_company' => $salesByCompany,
            'total_sales' => $totalSales,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ]);
    }
}