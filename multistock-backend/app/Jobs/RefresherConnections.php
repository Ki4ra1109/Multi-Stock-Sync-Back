<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\MercadoLibreCredential;
use Exception;
use App\Http\Controllers\MercadoLibre\Connections\testAndRefreshConnectionController;

class RefresherConnections implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $credentials = MercadoLibreCredential::pluck('client_id');

        if ($credentials->isEmpty()) {
            Log::info("No hay client_id registrados para refrescar.");
            return;
        }

        $controller = new testAndRefreshConnectionController();

        foreach ($credentials as $client_id) {
            try {
                $response = $controller->testAndRefreshConnection($client_id);

                if ($response->getStatusCode() == 200) {
                    Log::info("Token refrescado correctamente para client_id: {$client_id}");
                } else {
                    Log::error("Error al refrescar el token para client_id: {$client_id}, Código HTTP: " . $response->getStatusCode());
                }
            } catch (Exception $e) {
                Log::error("Fallo la conexión al refrescar el token para client_id: {$client_id}, Error: " . $e->getMessage());
            }
        }
    }
}