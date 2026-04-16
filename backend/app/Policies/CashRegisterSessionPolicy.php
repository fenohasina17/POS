<?php

namespace App\Policies;

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CashRegisterSessionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any cash register sessions.
     */
    public function viewAny(User $user)
    {
        // Example: allow all authenticated users to view sessions
        return $user !== null;
    }

    /**
     * Determine whether the user can view the cash register session.
     */
    public function view(User $user, CashRegisterSession $session)
    {
        // Example: allow if user is owner or has admin role
        return $user->id === $session->user_id || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create cash register sessions.
     */
    // public function create(User $user)
    // {
    //     // Example: allow if user has role caissier or admin
    //     return $user->hasAnyRole(['caissier', 'admin','gerant']);
    // }

    /**
     * Determine whether the user can update the cash register session.
     */
    public function update(User $user, CashRegisterSession $session)
    {
        // Example: allow if user is owner or has admin role
        return $user->id === $session->user_id || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the cash register session.
     */
    public function delete(User $user, CashRegisterSession $session)
    {
        // Example: allow only admin to delete
        return $user->hasRole('admin');
    }
}
