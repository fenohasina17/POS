<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Terminal;

class AlertService
{
    // Seuils configurables
    const OFFLINE_MINUTES  = 5;   // terminal offline si pas de heartbeat depuis 5 min
    const BACKLOG_WARNING  = 500;
    const BACKLOG_CRITICAL = 2000;

    public function checkAll(): void
    {
        foreach (Terminal::all() as $terminal) {
            $this->checkOffline($terminal);
            $this->checkSyncBacklog($terminal);
        }
    }

    private function checkOffline(Terminal $terminal): void
    {
        $isOffline = $terminal->last_heartbeat_at === null
            || $terminal->last_heartbeat_at->lt(now()->subMinutes(self::OFFLINE_MINUTES));

        $existing = Alert::where('terminal_id', $terminal->terminal_id)
            ->where('type', 'terminal_offline')
            ->whereNull('resolved_at')
            ->first();

        if ($isOffline && ! $existing) {
            Alert::create([
                'terminal_id'   => $terminal->terminal_id,
                'restaurant_id' => $terminal->restaurant_id,
                'type'          => 'terminal_offline',
                'severity'      => 'critical',
                'message'       => "Terminal {$terminal->terminal_id} hors ligne depuis plus de " . self::OFFLINE_MINUTES . " minutes.",
                'context'       => ['last_heartbeat_at' => $terminal->last_heartbeat_at?->toISOString()],
            ]);
        } elseif (! $isOffline && $existing) {
            $existing->update(['resolved_at' => now()]);
        }
    }

    private function checkSyncBacklog(Terminal $terminal): void
    {
        $count    = $terminal->pending_sync_count ?? 0;
        $existing = Alert::where('terminal_id', $terminal->terminal_id)
            ->where('type', 'sync_backlog')
            ->whereNull('resolved_at')
            ->first();

        if ($count >= self::BACKLOG_CRITICAL) {
            $this->upsertBacklogAlert($terminal, $count, 'critical', $existing);
        } elseif ($count >= self::BACKLOG_WARNING) {
            $this->upsertBacklogAlert($terminal, $count, 'warning', $existing);
        } elseif ($existing) {
            $existing->update(['resolved_at' => now()]);
        }
    }

    private function upsertBacklogAlert(Terminal $terminal, int $count, string $severity, ?Alert $existing): void
    {
        $data = [
            'terminal_id'   => $terminal->terminal_id,
            'restaurant_id' => $terminal->restaurant_id,
            'type'          => 'sync_backlog',
            'severity'      => $severity,
            'message'       => "Terminal {$terminal->terminal_id} : {$count} enregistrements en attente de synchronisation.",
            'context'       => ['pending_sync_count' => $count],
        ];

        if ($existing) {
            $existing->update($data);
        } else {
            Alert::create($data);
        }
    }
}
