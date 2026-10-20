<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BootstrapAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_the_admin_with_the_given_password()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101', '--password' => 'Secret123abc'])
            ->assertSuccessful();

        $admin = User::where('email', 'admin@igp.group')->firstOrFail();
        $this->assertTrue(Hash::check('Secret123abc', $admin->password));
        $this->assertTrue($admin->hasRole('admin'));
    }

    #[Test]
    public function it_generates_a_random_password_when_none_is_given()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101'])->assertSuccessful();

        $admin = User::where('email', 'admin@igp.group')->firstOrFail();
        $this->assertFalse(Hash::check('@dminInfoiGP', $admin->password));
    }

    #[Test]
    public function rerunning_keeps_the_existing_password()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101', '--password' => 'FirstPass123'])
            ->assertSuccessful();

        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101', '--password' => 'OtherPass456'])
            ->expectsOutputToContain('mot de passe inchangé')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@igp.group')->firstOrFail();
        $this->assertTrue(Hash::check('FirstPass123', $admin->password));
    }

    #[Test]
    public function reset_password_option_replaces_the_existing_password()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101', '--password' => 'FirstPass123'])
            ->assertSuccessful();

        $this->artisan('pos:bootstrap-admin', [
            'restaurant_id'    => '101',
            '--password'       => 'NewPass456',
            '--reset-password' => true,
        ])->assertSuccessful();

        $admin = User::where('email', 'admin@igp.group')->firstOrFail();
        $this->assertTrue(Hash::check('NewPass456', $admin->password));
    }

    #[Test]
    public function it_grants_cashier_and_manager_permissions()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101'])->assertSuccessful();

        $caissier = Role::findByName('caissier', 'api');
        foreach (['view.sales', 'create.sales', 'create.cash_register_sessions', 'update.cash_register_sessions', 'create.sale_payments'] as $permission) {
            $this->assertTrue($caissier->hasPermissionTo($permission, 'api'), "caissier sans {$permission}");
        }
        // Pas de modification ni de suppression de vente pour le caissier
        $this->assertFalse($caissier->hasPermissionTo('update.sales', 'api'));
        $this->assertFalse($caissier->hasPermissionTo('delete.sales', 'api'));

        $gerant = Role::findByName('gérant', 'api');
        $this->assertTrue($gerant->hasPermissionTo('view.sales', 'api'));
        $this->assertTrue($gerant->hasPermissionTo('update.products.status', 'api'));
        // Catalogue piloté par le Central
        $this->assertFalse($gerant->hasPermissionTo('update.products', 'api'));
    }

    #[Test]
    public function rerunning_keeps_manually_granted_permissions()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101'])->assertSuccessful();
        Role::findByName('caissier', 'api')->givePermissionTo('update.sales');

        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101'])->assertSuccessful();

        $this->assertTrue(Role::findByName('caissier', 'api')->hasPermissionTo('update.sales', 'api'));
    }

    #[Test]
    public function it_creates_default_payment_methods_including_cash()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101'])->assertSuccessful();

        // 'Espèce' : nom exact attendu par SaleService::isCashPayment()
        $this->assertDatabaseHas('payments', ['name' => 'Espèce']);
        $this->assertGreaterThan(1, Payment::count());
    }

    #[Test]
    public function rerunning_does_not_recreate_payment_methods_removed_by_the_admin()
    {
        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101'])->assertSuccessful();
        Payment::where('name', 'Airtel Money')->delete();

        $this->artisan('pos:bootstrap-admin', ['restaurant_id' => '101'])->assertSuccessful();

        $this->assertDatabaseMissing('payments', ['name' => 'Airtel Money']);
    }
}
