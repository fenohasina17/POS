<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableLockUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tableId;
    public $lockedBySessionId;

    public function __construct($tableId, $lockedBySessionId)
    {
        $this->tableId = $tableId;
        $this->lockedBySessionId = $lockedBySessionId;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('tables');
    }

    public function broadcastAs(): string
    {
        return 'table.updated';
    }
}
