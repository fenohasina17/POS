<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\RemoteCashRegisterSession;
use App\Models\RemoteSale;
use App\Models\Terminal;

class AlertService
{
    const OFFLINE_MINUTES      = 5;
    const BACKLOG_WARNING      = 500;
    const BACKLOG_CRITICAL     = 2000;
    const DISCREPANCY_CRITICAL = 50000; // Ar
    const CANCELLED_SPIKE      = 3;     // nb annulations / heure

    public function checkAll(): void
    {
        foreach (Terminal::all() as $terminal) {
            $this->checkOffline($terminal);
            $this->checkSyncBacklog($terminal);
        }
        $this->checkCashDiscrepancy();
        $this->checkCancelledSaleSpike();
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

    // Écart de caisse lors de la clôture d'une session (dernières 24h)
    private function checkCashDiscrepancy(): void
    {
        RemoteCashRegisterSession::where('has_discrepancy', true)
            ->whereNotNull('remote_closed_at')
            ->where('remote_closed_at', '>=', now()->subHours(24))
            ->each(function (RemoteCashRegisterSession $session) {
                $diff = abs(
                    floatval($session->actual_cash_amount) - floatval($session->expected_cash_amount)
                );
                $severity = $diff >= self::DISCREPANCY_CRITICAL ? 'critical' : 'warning';
                $amount   = number_format($diff, 0, ',', ' ');

                Alert::updateOrCreate(
                    [
                        'terminal_id' => $session->terminal_id,
                        'type'        => 'cash_discrepancy',
                        'resolved_at' => null,
                        // clé pour éviter doublon sur même session
                    ],
                    [
                        'restaurant_id' => $session->restaurant_id,
                        'severity'      => $severity,
                        'message'       => "Écart de caisse de {$amount} Ar sur {$session->terminal_id}.",
                        'context'       => [
                            'session_id' => $session->remote_id,
                            'expected'   => $session->expected_cash_amount,
                            'actual'     => $session->actual_cash_amount,
                            'diff'       => $diff,
                        ],
                    ]
                );
            });
    }

    // Pic de ventes annulées (≥3 dans la dernière heure sur un même terminal)
    private function checkCancelledSaleSpike(): void
    {
        RemoteSale::where('status', 'cancelled')
            ->where('remote_created_at', '>=', now()->subHour())
            ->selectRaw('terminal_id, restaurant_id, COUNT(*) as nb')
            ->groupBy('terminal_id', 'restaurant_id')
            ->having('nb', '>=', self::CANCELLED_SPIKE)
            ->each(function ($group) {
                Alert::updateOrCreate(
                    [
                        'terminal_id' => $group->terminal_id,
                        'type'        => 'cancelled_sales_spike',
                        'resolved_at' => null,
                    ],
                    [
                        'restaurant_id' => $group->restaurant_id,
                        'severity'      => 'warning',
                        'message'       => "{$group->nb} ventes annulées sur {$group->terminal_id} dans la dernière heure.",
                        'context'       => ['count' => $group->nb, 'window_minutes' => 60],
                    ]
                );
            });
    }
}
