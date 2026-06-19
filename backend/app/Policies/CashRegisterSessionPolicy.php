<?php

namespace App\Policies;

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CashRegisterSessionPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, CashRegisterSession $session)
    {
        // Un caissier voit sa session, un gérant voit les sessions de son POS
        if ($user->isManager()) {
            return $user->pointsOfSale->contains($session->cashRegister->point_of_sale_id);
        }
        return $user->id === $session->user_id;
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, CashRegisterSession $session)
    {
        if ($user->isManager()) {
            return $user->pointsOfSale->contains($session->cashRegister->point_of_sale_id);
        }
        return $user->id === $session->user_id;
    }

    public function delete(User $user, CashRegisterSession $session)
    {
        return false; // Only admin
    }
}
