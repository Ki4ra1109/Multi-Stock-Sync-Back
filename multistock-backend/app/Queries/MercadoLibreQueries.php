<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;

/**
 * MercadoLibre Queries Class
 * This class is used to make custom queries to MercadoLibre API
 * You can add new methods here to create specific queries
 */
class MercadoLibreQueries
{
    /**
     * Obtener solo los títulos de los productos
     */
    public function getProductTitles($clientId)
    {
        return DB::select("
            SELECT title 
            FROM mercadolibre_products 
            WHERE client_id = ?
            ORDER BY title ASC
        ", [$clientId]);
    }

    /**
     * Obtener solo los títulos de los productos desde la API
     */
    public function getProductTitlesFromApi($clientId)
    {
        // Obtener credenciales
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
        
        if (!$credentials) {
            throw new \Exception('Valid credentials not found');
        }

        // Obtener ID del usuario
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');
        
        $userId = $userResponse->json()['id'];

        // Obtener productos
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search");

        if ($response->failed()) {
            throw new \Exception('Error getting products');
        }

        $productIds = $response->json()['results'];
        $titles = [];

        // Solo obtener títulos de los productos
        foreach ($productIds as $productId) {
            $productResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$productId}");

            if ($productResponse->successful()) {
                $titles[] = [
                    'title' => $productResponse->json()['title']
                ];
            }
        }

        return $titles;
    }

    /**
     * Save products from API to database
     */
    public function saveProductsFromApi($clientId)
    {
        // Obtener credenciales directamente del modelo
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
        
        if (!$credentials) {
            throw new \Exception('Valid credentials not found');
        }

        // Obtener ID del usuario
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');
        
        if ($userResponse->failed()) {
            throw new \Exception('Error getting user data: ' . $userResponse->body());
        }

        $userId = $userResponse->json()['id'];

        // Obtener productos
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search");

        if ($response->failed()) {
            throw new \Exception('Error getting products: ' . $response->body());
        }

        $productIds = $response->json()['results'];
        $savedProducts = 0;

        // Guardar cada producto en la base de datos
        foreach ($productIds as $productId) {
            try {
                $productResponse = Http::withToken($credentials->access_token)
                    ->get("https://api.mercadolibre.com/items/{$productId}");

                if ($productResponse->successful()) {
                    $productData = $productResponse->json();
                    
                    DB::table('mercadolibre_products')->updateOrInsert(
                        ['ml_id' => $productId],
                        [
                            'client_id' => $clientId,
                            'title' => $productData['title'] ?? '',
                            'price' => $productData['price'] ?? 0,
                            'currency_id' => $productData['currency_id'] ?? '',
                            'available_quantity' => $productData['available_quantity'] ?? 0,
                            'sold_quantity' => $productData['sold_quantity'] ?? 0,
                            'thumbnail' => $productData['thumbnail'] ?? '',
                            'permalink' => $productData['permalink'] ?? '',
                            'status' => $productData['status'] ?? '',
                            'category_id' => $productData['category_id'] ?? '',
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                    
                    $savedProducts++;
                }
            } catch (\Exception $e) {
                \Log::error('Error saving product: ' . $e->getMessage());
                continue;
            }
        }

        if ($savedProducts === 0) {
            throw new \Exception('No products were saved');
        }

        return $savedProducts;
    }

    /**
     * Save products using SQL INSERT
     */
    public function saveProductsWithSQL($clientId)
    {
        // Obtener credenciales
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
        
        if (!$credentials) {
            throw new \Exception('Valid credentials not found');
        }

        // Obtener ID del usuario
        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');
        
        $userId = $userResponse->json()['id'];

        // Obtener productos
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/users/{$userId}/items/search");

        if ($response->failed()) {
            throw new \Exception('Error getting products');
        }

        $productIds = $response->json()['results'];
        $savedProducts = 0;

        // Construir la consulta SQL INSERT
        $sql = "INSERT INTO mercadolibre_products (
            ml_id, 
            client_id, 
            title, 
            price, 
            currency_id, 
            available_quantity, 
            sold_quantity, 
            thumbnail, 
            permalink, 
            status, 
            category_id, 
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            price = VALUES(price),
            currency_id = VALUES(currency_id),
            available_quantity = VALUES(available_quantity),
            sold_quantity = VALUES(sold_quantity),
            thumbnail = VALUES(thumbnail),
            permalink = VALUES(permalink),
            status = VALUES(status),
            category_id = VALUES(category_id),
            updated_at = NOW()";
        
        //Ciclo para insertar cada producto
        foreach ($productIds as $productId) {
            $productResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/items/{$productId}");

            if ($productResponse->successful()) {
                $productData = $productResponse->json();
                
                // Ejecutar la consulta SQL
                DB::insert($sql, [
                    $productData['id'],          // ml_id
                    $clientId,                   // client_id
                    $productData['title'],       
                    $productData['price'],       
                    $productData['currency_id'], 
                    $productData['available_quantity'],
                    $productData['sold_quantity'],
                    $productData['thumbnail'],
                    $productData['permalink'],
                    $productData['status'],
                    $productData['category_id']
                ]);
                
                $savedProducts++;
            }
        }

        return $savedProducts;
    }
} 