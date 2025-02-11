<?php

namespace App\Jobs;

use App\Models\SyncStatus;
use App\Models\Product; //FALTA HACER EL PRODUCT MODEL MI GENTE
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

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
        $sync = SyncStatus::where('status', 'in_progress')->first();

        if (!$sync) {
            return;
        }

        $sync->start_time = now();
        $sync->total_products = count($this->products);
        $sync->save();

        $processed = 0;

        foreach ($this->products as $productData) {
            Product::create($productData);
            $processed++;

            $sync->processed_products = $processed;
            $sync->save();

            // Emitir evento WebSocket para actualizar el frontend
            Broadcast::channel('sync-status', function () use ($sync) {
                return ['processed' => $sync->processed_products, 'total' => $sync->total_products];
            });

            sleep(1); // Simulamos tiempo de proceso
        }

        $sync->status = 'completed';
        $sync->end_time = now();
        $sync->save();
    }
}

