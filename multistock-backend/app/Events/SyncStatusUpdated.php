<?php

namespace App\Events;

use App\Models\SyncStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class SyncStatusUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $syncStatus;

    public function __construct(SyncStatus $syncStatus)
    {
        $this->syncStatus = $syncStatus;
    }

    public function broadcastOn()
    {
        return new Channel('sync-status');
    }

    public function broadcastWith()
    {
        return [
            'processed' => $this->syncStatus->processed_products,
            'total' => $this->syncStatus->total_products,
            'status' => $this->syncStatus->status
        ];
    }
}


