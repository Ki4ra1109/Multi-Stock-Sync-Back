<?php

namespace App\Jobs;

use App\Models\SyncStatus;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $productos;

    public function __construct($productos)
    {
        $this->productos = $productos;
    }

    public function handle()
    {
        // Buscar si hay una sincronización en progreso
        $sincronizacion = SyncStatus::where('status', 'in_progress')->first();

        if (!$sincronizacion) {
            Log::warning('No hay sincronización en progreso. Terminando job.');
            return;
        }

        // Marcar el inicio de la sincronización
        $sincronizacion->start_time = now();
        $sincronizacion->total_products = count($this->productos);
        $sincronizacion->save();

        $procesados = 0;

        foreach ($this->productos as $productoData) {
            try {
                Product::create($productoData);
                $procesados++;

                // Actualizar la cantidad de productos procesados
                $sincronizacion->processed_products = $procesados;
                $sincronizacion->updated_at = now();
                $sincronizacion->save();

                sleep(1); // Simulación de un proceso más lento

            } catch (\Exception $e) {
                Log::error('Error al procesar producto: ' . $e->getMessage());
            }
        }

        // Finalizar la sincronización
        $sincronizacion->status = 'completed';
        $sincronizacion->end_time = now();
        $sincronizacion->save();

        Log::info('Sincronización de productos completada con éxito.');
    }
}
