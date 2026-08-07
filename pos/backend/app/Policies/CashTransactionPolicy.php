<?php

namespace App\Policies;

use App\Models\CashTransaction;
use App\Models\User;

class CashTransactionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create.transactions');
    }

    public function update(User $user, CashTransaction $cashTransaction): bool
    {
        if ($cashTransaction->session && $cashTransaction->session->isClosed()) {
            return false;
        }
        return $user->hasPermissionTo('create.transactions');
    }

    public function delete(User $user, CashTransaction $cashTransaction): bool
    {
        if (!$user->hasPermissionTo('delete.transactions')) {
            return false;
        }

        $session = $cashTransaction->session;
        if ($session && $session->isClosed()) {
            return false;
        }

        return true;
    }
}
