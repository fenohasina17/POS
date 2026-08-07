<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SessionDiscrepancyPolicy
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

    public function view(User $user, $model)
    {
        return true;
    }

    public function create(User $user)
    {
        return $user->isManager();
    }

    public function update(User $user, $model)
    {
        return $user->isManager();
    }

    public function delete(User $user, $model)
    {
        return false;
    }
}
