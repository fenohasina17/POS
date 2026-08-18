<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->pointsOfSale->contains($sale->point_of_sale_id);
    }

    public function create(User $user): bool
    {
        // Caissiers and managers can create sales
        return true; 
    }

    public function update(User $user, Sale $sale): bool
    {
        // Manager can update their POS sales, cashier only their own sales
        if ($user->isManager()) {
            return $user->pointsOfSale->contains($sale->point_of_sale_id);
        }
        return $user->id === $sale->user_id;
    }

    public function delete(User $user, Sale $sale): bool
    {
        if ($user->isManager()) {
            return $user->pointsOfSale->contains($sale->point_of_sale_id);
        }
        return false;
    }
}
