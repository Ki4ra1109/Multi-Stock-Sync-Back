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
        $sincronizacion = SyncStatus::where('estado', 'en_progreso')->first();

        if (!$sincronizacion) {
            return;
        }

        $sincronizacion->inicio = now();
        $sincronizacion->total_productos = count($this->productos);
        $sincronizacion->save();

        $procesados = 0;

        foreach ($this->productos as $productoData) {
            Product::create($productoData);
            $procesados++;

            $sincronizacion->productos_procesados = $procesados;
            $sincronizacion->save();

            sleep(1);
        }

        $sincronizacion->estado = 'completado';
        $sincronizacion->fin = now();
        $sincronizacion->save();
    }
}