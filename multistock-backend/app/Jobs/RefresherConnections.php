<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\MercadoLibreCredential;
use Exception;

class RefresherConnections implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $apiBaseUrl = config('services.mercadolibre.api_url', env('MERCADOLIBRE_API_URL'));

        if (!$apiBaseUrl) {
            Log::error("No se encontró la URL base para la API en .env");
            return;
        }


        $credentials = MercadoLibreCredential::pluck('client_id');

        if ($credentials->isEmpty()) {
            Log::info("No hay client_id registrados para refrescar.");
            return;
        }

        foreach ($credentials as $client_id) {
            $url = "{$apiBaseUrl}/api/MercadoLibre/connections/test-connection/{$client_id}";

            try {

                $response = Http::timeout(10)->get($url);

                if ($response->successful()) {
                    Log::info("Token refrescado correctamente para client_id: {$client_id}");
                } else {
                    Log::error("Error al refrescar el token para client_id: {$client_id}, Código HTTP: " . $response->status());
                }
            } catch (Exception $e) {
                Log::error("Fallo la conexión al refrescar el token para client_id: {$client_id}, Error: " . $e->getMessage());
            }
        }
    }
}