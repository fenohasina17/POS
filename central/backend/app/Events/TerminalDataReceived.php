<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TerminalDataReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $terminalId,
        public readonly string $restaurantId,
        public readonly string $resource,
        public readonly int    $recordsInserted,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('central-dashboard'),
            new Channel("restaurant.{$this->restaurantId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'terminal.data';
    }

    public function broadcastWith(): array
    {
        return [
            'terminal_id'      => $this->terminalId,
            'restaurant_id'    => $this->restaurantId,
            'resource'         => $this->resource,
            'records_inserted' => $this->recordsInserted,
            'at'               => now()->toIso8601String(),
        ];
    }
}
