<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

class UserRoleFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            // User factory fields here if needed
        ];
    }

    /**
     * Attach roles to the user.
     */
    public function configure()
    {
        return $this->afterCreating(function (User $user) {
            // Create roles if they don't exist
            $roles = ['admin', 'gerant', 'caissier'];
            foreach ($roles as $roleName) {
                Role::firstOrCreate(['name' => $roleName]);
            }

            // Attach roles to user - example: assign all roles, adjust as needed
            $user->assignRole('caissier'); // default role
        });
    }
}
