<?php
// app/Policies/CashTransactionPolicy.php

namespace App\Policies;

use App\Models\CashTransaction;
use App\Models\User;

class CashTransactionPolicy
{
    public function create(User $user): bool
    {
<<<<<<< HEAD
        return $user->hasPermissionTo('create.transactions');
=======
        // On vérifie la permission Spatie
        return $user->hasPermissionTo('create.cash_transactions');
>>>>>>> 85008940c5a48f81fac431603537bfe947e1baad
    }

    public function update(User $user, CashTransaction $cashTransaction): bool
    {
<<<<<<< HEAD
        // Vérifier que la session n'est pas fermée
        if ($cashTransaction->session && $cashTransaction->session->isClosed()) {
            return false;
        }
        return $user->hasPermissionTo('create.transactions');
=======
        // On peut utiliser la même permission ou une spécifique
        return $user->hasPermissionTo('create.cash_transactions');
>>>>>>> 85008940c5a48f81fac431603537bfe947e1baad
    }

   // app/Policies/CashTransactionPolicy.php

public function delete(User $user, CashTransaction $cashTransaction): bool
{
    \Log::info('=== POLICY DELETE DEBUG ===');
    \Log::info('User ID: ' . $user->id);
    \Log::info('Transaction ID: ' . $cashTransaction->id);
    
    // Vérifier la permission
    $hasPermission = $user->hasPermissionTo('delete.transactions');
    \Log::info('Has delete.transactions permission: ' . ($hasPermission ? 'YES' : 'NO'));
    
    if (!$hasPermission) {
        \Log::info('Permission denied');
        return false;
    }
    
    // Vérifier la session
    $session = $cashTransaction->session;
    \Log::info('Session exists: ' . ($session ? 'YES' : 'NO'));
    
    if ($session) {
        \Log::info('Session is_closed value: ' . $session->is_closed);
        \Log::info('Session is_closed (bool): ' . ($session->is_closed ? 'true' : 'false'));
        \Log::info('Session isClosed() method: ' . ($session->isClosed() ? 'true' : 'false'));
    }
    
    if ($session && $session->isClosed()) {
        \Log::info('Session is closed - delete denied');
        return false;
    }
    
    \Log::info('Delete authorized');
    return true;
}

}