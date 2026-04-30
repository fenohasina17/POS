<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SalePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, array $data): bool
    {
        $isAdmin = $user->hasRole('admin', 'api');

        // SÉCURITÉ 1 : POS assigné
        if (!$isAdmin && (int) $user->point_of_sale_id !== (int) $data['point_of_sale_id']) {
            return false;
        }

        // SÉCURITÉ 2 : Ne peut créer que pour soi-même
        if (!$isAdmin && (int) $data['user_id'] !== (int) $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sale $sale): bool
    {
        return false;
    }
    
}
