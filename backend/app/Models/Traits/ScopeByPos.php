<?php

namespace App\Models\Traits;

use App\Models\User;
use App\Enums\RoleEnum;

trait ScopeByPos
{
    /**
     * Scope a query to only include records accessible by the given user.
     * 
     * If the user is an admin, they can see everything.
     * Otherwise, they only see records linked to their assigned points of sale.
     * 
     * Assumes the model either has a `point_of_sale_id` column
     * or a `pointsOfSale` relation.
     */
    public function scopeForCurrentUser($query, User $user = null)
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query;
        }

        if ($user->hasRole(RoleEnum::ADMIN->value, 'api')) {
            return $query;
        }

        $posIds = $user->pointsOfSale->pluck('id')->toArray();

        // Check if model has a point_of_sale_id foreign key
        if (in_array('point_of_sale_id', $this->getFillable()) || $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'point_of_sale_id')) {
            return $query->whereIn($this->getTable() . '.point_of_sale_id', $posIds);
        }

        // Otherwise assume it uses a relationship (like Product)
        if (method_exists($this, 'pointsOfSale')) {
            return $query->whereHas('pointsOfSale', function ($q) use ($posIds) {
                $q->whereIn('point_of_sales.id', $posIds); // Assuming table is point_of_sales
            });
        }

        return $query;
    }
}
