<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanOrphanTableLocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-orphan-table-locks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $now = now();

        // 1. Libérer les verrous des sessions fermées
        $closedSessions = \App\Models\CashRegisterSession::where('is_closed', true)->pluck('id');
        $tables = \App\Models\Table::whereIn('locked_by_session_id', $closedSessions)
            ->update(['locked_by_session_id' => null, 'locked_at' => null]);

        // 2. Libérer les verrous orphelins (session supprimée)
        \App\Models\Table::whereNotNull('locked_by_session_id')
            ->whereDoesntHave('lockedBySession')
            ->update(['locked_by_session_id' => null, 'locked_at' => null]);

        $this->info("Nettoyage des verrous effectué avec succès.");
    }
}
