<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
