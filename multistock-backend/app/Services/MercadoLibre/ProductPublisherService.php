<?php

namespace App\Services\MercadoLibre;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductPublisherService
{
    public function publishToAllStores(array $productData): void
    {
        $credentials = MercadoLibreCredential::whereNotNull('access_token')->get();

        foreach ($credentials as $credential) {
            $clientId = $credential->client_id;
            $accessToken = $credential->access_token;

            try {
                $response = Http::withToken($accessToken)
                    ->post('https://api.mercadolibre.com/items', $productData);

                Log::info("📦 Producto enviado a $clientId", [
                    'status' => $response->status(),
                    'client_id' => $clientId,
                    'response' => $response->json()
                ]);
            } catch (\Throwable $e) {
                Log::error("❌ Error al enviar producto a $clientId", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

// Esto nos sirve para publicar productos en múltiples tiendas de Mercado Libre utilizando las credenciales almacenadas en la base de datos.