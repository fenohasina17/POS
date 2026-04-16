<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create users with roles using the UserRoleFactory
        User::factory()->count(1)->create()->each(function ($user) {
            $user->assignRole('admin');
        });

        User::factory()->count(1)->create()->each(function ($user) {
            $user->assignRole('gerant');
        });

        User::factory()->count(1)->create()->each(function ($user) {
            $user->assignRole('caissier');
        });
    }
}
