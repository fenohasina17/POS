<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\RemoteCashRegisterSession;
use App\Models\RemoteSale;
use App\Models\Terminal;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckAlerts extends Command
{
    protected $signature   = 'alerts:check';
    protected $description = 'Vérifie les conditions d\'alerte et crée les alertes manquantes';

    // Seuils configurables
    private const OFFLINE_MINUTES   = 10;   // terminal hors ligne
    private const BACKLOG_THRESHOLD = 100;  // enregistrements en attente

    public function handle(): int
    {
        $this->checkTerminalOffline();
        $this->checkSyncBacklog();
        $this->checkCashDiscrepancy();
        $this->checkCancelledSales();
        $this->autoResolveOnlineTerminals();

        return self::SUCCESS;
    }

    // Terminal sans heartbeat depuis OFFLINE_MINUTES minutes
    private function checkTerminalOffline(): void
    {
        $cutoff = now()->subMinutes(self::OFFLINE_MINUTES);

        Terminal::where(function ($q) use ($cutoff) {
            $q->where('last_heartbeat_at', '<', $cutoff)
              ->orWhereNull('last_heartbeat_at');
        })->each(function (Terminal $terminal) {
            $this->upsertAlert(
                terminal_id:   $terminal->terminal_id,
                restaurant_id: $terminal->restaurant_id,
                type:          'terminal_offline',
                severity:      'critical',
                message:       "Le terminal {$terminal->terminal_id} est hors ligne.",
                context:       ['last_seen' => $terminal->last_heartbeat_at?->toIso8601String()],
            );
        });
    }

    // Trop d'enregistrements en attente de sync
    private function checkSyncBacklog(): void
    {
        Terminal::whereNotNull('pending_sync_count')
            ->where('pending_sync_count', '>=', self::BACKLOG_THRESHOLD)
            ->each(function (Terminal $terminal) {
                $this->upsertAlert(
                    terminal_id:   $terminal->terminal_id,
                    restaurant_id: $terminal->restaurant_id,
                    type:          'sync_backlog',
                    severity:      'warning',
                    message:       "{$terminal->pending_sync_count} enregistrements en attente sur {$terminal->terminal_id}.",
                    context:       ['pending_count' => $terminal->pending_sync_count],
                );
            });
    }

    // Écart de caisse détecté lors de la clôture d'une session
    private function checkCashDiscrepancy(): void
    {
        // Alertes pour les sessions avec écart non encore signalées
        RemoteCashRegisterSession::where('has_discrepancy', true)
            ->whereNotNull('remote_closed_at')
            ->where('remote_closed_at', '>=', now()->subHours(24))
            ->each(function (RemoteCashRegisterSession $session) {
                $diff = abs(
                    floatval($session->actual_cash_amount) - floatval($session->expected_cash_amount)
                );
                $severity = $diff > 50000 ? 'critical' : 'warning';

                $this->upsertAlert(
                    terminal_id:   $session->terminal_id,
                    restaurant_id: $session->restaurant_id,
                    type:          'cash_discrepancy',
                    severity:      $severity,
                    message:       "Écart de caisse détecté sur {$session->terminal_id} : " . number_format($diff, 0, ',', ' ') . " Ar.",
                    context:       [
                        'session_id'  => $session->remote_id,
                        'expected'    => $session->expected_cash_amount,
                        'actual'      => $session->actual_cash_amount,
                        'diff'        => $diff,
                    ],
                );
            });
    }

    // Ventes annulées en dehors des heures normales ou en masse
    private function checkCancelledSales(): void
    {
        $window = now()->subHour();

        $groups = RemoteSale::where('status', 'cancelled')
            ->where('remote_created_at', '>=', $window)
            ->selectRaw('terminal_id, restaurant_id, COUNT(*) as nb')
            ->groupBy('terminal_id', 'restaurant_id')
            ->having('nb', '>=', 3)
            ->get();

        foreach ($groups as $group) {
            $this->upsertAlert(
                terminal_id:   $group->terminal_id,
                restaurant_id: $group->restaurant_id,
                type:          'cancelled_sales_spike',
                severity:      'warning',
                message:       "{$group->nb} ventes annulées sur {$group->terminal_id} dans la dernière heure.",
                context:       ['count' => $group->nb, 'window_minutes' => 60],
            );
        }
    }

    // Résoudre automatiquement les alertes offline quand le terminal revient en ligne
    private function autoResolveOnlineTerminals(): void
    {
        $onlineIds = Terminal::where('status', 'online')
            ->where('last_heartbeat_at', '>=', now()->subMinutes(self::OFFLINE_MINUTES))
            ->pluck('terminal_id');

        Alert::whereIn('terminal_id', $onlineIds)
            ->where('type', 'terminal_offline')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    // Crée ou met à jour une alerte (évite les doublons)
    private function upsertAlert(
        string  $terminal_id,
        ?string $restaurant_id,
        string  $type,
        string  $severity,
        string  $message,
        array   $context = [],
    ): void {
        Alert::updateOrCreate(
            [
                'terminal_id' => $terminal_id,
                'type'        => $type,
                'resolved_at' => null,
            ],
            [
                'restaurant_id' => $restaurant_id,
                'severity'      => $severity,
                'message'       => $message,
                'context'       => $context,
            ]
        );
    }
}
