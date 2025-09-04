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
use Illuminate\Support\Facades\Cache;

class getCompaniesProductsController extends Controller
{
    public function getTotalSalesAllCompanies(Request $request)
    {
        $clientIds = Company::whereNotNull('client_id')->pluck('client_id')->toArray();
        $companyNames = Company::whereNotNull('client_id')->pluck('name', 'client_id')->toArray();

        // Obtener año y mes desde la query
        
        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('m'));

        
        $start = \Carbon\Carbon::create($year, $month, 1, 0, 0, 0, 'UTC')->startOfMonth();
        $end = \Carbon\Carbon::create($year, $month, 1, 0, 0, 0, 'UTC')->endOfMonth();
        
        $dateFrom = $start->toIso8601String();
        $dateTo = $end->toIso8601String();

        $allSales = [];
        $totalSales = 0;
        $ordersCounted = []; // [orderId] => true
        $client = new Client(['timeout' => 20]);
        $promises = [];


        foreach ($clientIds as $clientId) {
            Log::info("Procesando empresa", ['client_id' => $clientId]);

            // Cachear credenciales por 10 minutos
            $cacheKey = 'ml_credentials_' . $clientId;
            $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
                Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
                return MercadoLibreCredential::where('client_id', $clientId)->first();
            });

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

            $limit = 50;
            $page = 0;
            $hasMore = true;
            $clientOrders = [];
            $maxRetries = 3;
            while ($hasMore) {
                $params = [
                    'seller' => $userId,
                    'order.status' => 'paid',
                    'order.date_created.from' => $dateFrom,
                    'order.date_created.to' => $dateTo,
                    'limit' => $limit,
                    'offset' => $page * $limit
                ];
                $retries = 0;
                do {
                    try {
                        $response = $client->get('https://api.mercadolibre.com/orders/search', [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $credentials->access_token,
                            ],
                            'query' => $params
                        ]);
                        $statusCode = $response->getStatusCode();
                    } catch (\Exception $e) {
                        Log::error("Error de red al consultar órdenes para client_id: $clientId, intento $retries", [
                            'exception' => $e->getMessage(),
                            'page' => $page
                        ]);
                        $statusCode = 0;
                    }
                    $retries++;
                } while ($statusCode !== 200 && $retries < $maxRetries);

                if ($statusCode === 200) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    $results = $data['results'] ?? [];
                    if (count($results) === 0) {
                        $hasMore = false;
                    } else {
                        $clientOrders = array_merge($clientOrders, $results);
                        if (count($results) < $limit) {
                            $hasMore = false;
                        }
                    }
                } else {
                    Log::error("No se pudo obtener todas las páginas para client_id: $clientId, página $page después de $maxRetries intentos.");
                    $hasMore = false;
                }
                $page++;
            }

            // Procesa $clientOrders
            $processedOrders = []; // [clientId][orderMonth][orderId] => true
            foreach ($clientOrders as $order) {
                $orderDate = \Carbon\Carbon::parse($order['date_created']);
                if ($orderDate->year != $year || $orderDate->month != $month) {
                    continue;
                }
                $orderMonth = $orderDate->format('Y-m');
                $orderId = $order['id'];

                
                if (isset($processedOrders[$clientId][$orderMonth][$orderId])) {
                    
                    Log::warning("Orden duplicada detectada", [
                        'client_id' => $clientId,
                        'order_id' => $orderId,
                        'order_month' => $orderMonth
                    ]);
                    continue;
                }
                $processedOrders[$clientId][$orderMonth][$orderId] = true;

                
                if (isset($globalProcessedOrders[$orderId])) {
                    Log::warning("Orden pagada aparece en varias empresas", [
                        'order_id' => $orderId,
                        'empresas' => $globalProcessedOrders[$orderId],
                        'empresa_actual' => $clientId
                    ]);
                }
                $globalProcessedOrders[$orderId][] = $clientId;

                if (!isset($salesByCompany[$orderMonth][$clientId])) {
                    $salesByCompany[$orderMonth][$clientId] = [
                        'total_sales' => 0,
                        'total_products' => 0,
                        'products' => []
                    ];
                }
                if (isset($order['total_amount'])) {
                    $salesByCompany[$orderMonth][$clientId]['total_sales'] += $order['total_amount'];
                    
                    if (!isset($ordersCounted[$orderId])) {
                        $totalSales += $order['total_amount'];
                        $ordersCounted[$orderId] = true;
                    }
                }
                
                
                foreach ($order['order_items'] as $item) {
                    $product = [
                        'title' => $item['item']['title'] ?? null,
                        'quantity' => $item['quantity'] ?? 0,
                        'price' => $item['unit_price'] ?? 0,
                        'order_id' => $orderId,
                        'date_created' => $order['date_created'] ?? null,
                    ];
                    $salesByCompany[$orderMonth][$clientId]['products'][] = $product;
                    $salesByCompany[$orderMonth][$clientId]['total_products'] += $item['quantity'] ?? 0;
                }

            }
        }

       foreach ($salesByCompany as $clientId => &$months) {
       ksort($months);
}
       unset($months);
        $salesByCompany = array_map(function ($months, $monthKey) use ($companyNames) {
            return array_map(function ($data, $clientId) use ($companyNames) {
                return [
                    'total_sales' => $data['total_sales'],
                    'total_products' => $data['total_products'],
                    'products' => $data['products'],
                    'company_name' => $companyNames[$clientId] ?? 'Desconocida',
                ];
            }, $months, array_keys($months));
        }, $salesByCompany, array_keys($salesByCompany));

        

        return response()->json([
            'status' => 'success',
            'message' => 'Órdenes pagadas agrupadas por empresa',
            'sales_by_company' => $salesByCompany,
            'total_sales' => $totalSales,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            
        ]);
    }
}


