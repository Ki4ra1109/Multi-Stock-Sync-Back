<?php

namespace App\Jobs;

use App\Models\SyncStatus;
use App\Models\Product;
use App\Events\SyncStatusUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function handle()
    {
        // Obtener el estado de sincronización en progreso
        $sync = SyncStatus::where('status', 'in_progress')->first();

        if (!$sync) {
            return;
        }

        $sync->start_time = now();
        $sync->total_products = count($this->products);
        $sync->save();

        $processed = 0;

        foreach ($this->products as $productData) {
            try {
                Product::create($productData);
                $processed++;

                $sync->processed_products = $processed;
                $sync->save();

                // Emitir evento para notificar al frontend
                broadcast(new SyncStatusUpdated($sync));

                sleep(1); // Simula tiempo de procesamiento

            } catch (\Exception $e) {
                Log::error('Error al sincronizar producto: ' . $e->getMessage());
            }
        }

        // Finalizar la sincronización
        $sync->status = 'completed';
        $sync->end_time = now();
        $sync->save();

        // Emitir evento final de actualización
        broadcast(new SyncStatusUpdated($sync));
    }
}


