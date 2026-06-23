<?php

namespace Tests\Feature;

use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserPermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $targetUser;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');

        $pos              = PointOfSale::factory()->create();
        $this->admin      = User::factory()->create(['point_of_sale_id' => $pos->id]);
        $this->targetUser = User::factory()->create(['point_of_sale_id' => $pos->id]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // GET /api/users/{user}/permissions
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_permissions_d_un_utilisateur()
    {
        $permission = Permission::findOrCreate('view.sales', 'api');
        $this->targetUser->givePermissionTo($permission);

        $response = $this->asAdmin()->getJson("/api/users/{$this->targetUser->id}/permissions");

        $response->assertStatus(200)
                 ->assertJsonIsArray();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('view.sales'));
    }

    #[Test]
    public function utilisateur_sans_permissions_retourne_tableau_vide()
    {
        $response = $this->asAdmin()->getJson("/api/users/{$this->targetUser->id}/permissions");

        $response->assertStatus(200)
                 ->assertJsonIsArray()
                 ->assertExactJson([]);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_permissions_utilisateur()
    {
        $this->getJson("/api/users/{$this->targetUser->id}/permissions")
             ->assertStatus(401);
    }

    // =========================================================================
    // GET /api/users/{user}/permissions/{permission}/check
    // =========================================================================

    #[Test]
    public function admin_peut_verifier_si_utilisateur_a_une_permission()
    {
        $permission = Permission::findOrCreate('export.sales', 'api');
        $this->targetUser->givePermissionTo($permission);

        $response = $this->asAdmin()
             ->getJson("/api/users/{$this->targetUser->id}/permissions/{$permission->id}/check");

        $response->assertStatus(200)
                 ->assertJsonPath('has_permission', true);
    }

    #[Test]
    public function check_retourne_false_si_permission_non_attribuee()
    {
        $permission = Permission::findOrCreate('delete.products', 'api');

        $response = $this->asAdmin()
             ->getJson("/api/users/{$this->targetUser->id}/permissions/{$permission->id}/check");

        $response->assertStatus(200)
                 ->assertJsonPath('has_permission', false);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_verifier_les_permissions()
    {
        $permission = Permission::findOrCreate('view.sales', 'api');

        $this->getJson("/api/users/{$this->targetUser->id}/permissions/{$permission->id}/check")
             ->assertStatus(401);
    }
}
