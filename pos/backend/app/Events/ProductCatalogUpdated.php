<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductCatalogUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $created;
    public array $updated;

    public function __construct(array $created, array $updated)
    {
        $this->created = $created;
        $this->updated = $updated;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('catalog');
    }
}
