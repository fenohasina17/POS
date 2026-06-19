<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PointOfSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'api');

        $pos = PointOfSale::factory()->create();
        $this->admin = User::factory()->create(['point_of_sale_id' => $pos->id]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function asAdmin(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    // =========================================================================
    // GET /api/roles
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_roles()
    {
        Role::findOrCreate('gérant', 'api');
        Role::findOrCreate('caissier', 'api');

        $response = $this->asAdmin()->getJson('/api/roles');

        $response->assertStatus(200)
                 ->assertJsonIsArray();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('admin'));
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_roles()
    {
        $this->getJson('/api/roles')->assertStatus(401);
    }

    // =========================================================================
    // POST /api/roles
    // =========================================================================

    #[Test]
    public function admin_peut_creer_un_role()
    {
        $response = $this->asAdmin()->postJson('/api/roles', ['name' => 'superviseur']);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'superviseur')
                 ->assertJsonPath('guard_name', 'api');

        $this->assertDatabaseHas('roles', ['name' => 'superviseur', 'guard_name' => 'api']);
    }

    #[Test]
    public function creation_echoue_si_nom_manquant()
    {
        $this->asAdmin()
             ->postJson('/api/roles', [])
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['name']]);
    }

    #[Test]
    public function creation_echoue_si_nom_deja_utilise()
    {
        $this->asAdmin()
             ->postJson('/api/roles', ['name' => 'admin'])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_un_role()
    {
        $this->postJson('/api/roles', ['name' => 'test'])->assertStatus(401);
    }

    // =========================================================================
    // GET /api/roles/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_voir_un_role()
    {
        $role = Role::findOrCreate('gérant', 'api');

        $this->asAdmin()
             ->getJson("/api/roles/{$role->id}")
             ->assertStatus(200)
             ->assertJsonPath('name', 'gérant');
    }

    #[Test]
    public function retourne_404_pour_role_inexistant()
    {
        $this->asAdmin()->getJson('/api/roles/99999')->assertStatus(404);
    }

    // =========================================================================
    // PUT /api/roles/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_modifier_un_role()
    {
        $role = Role::findOrCreate('gérant', 'api');

        $this->asAdmin()
             ->putJson("/api/roles/{$role->id}", ['name' => 'manager'])
             ->assertStatus(200)
             ->assertJsonPath('name', 'manager');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'manager']);
    }

    #[Test]
    public function modification_echoue_si_nom_deja_pris()
    {
        Role::findOrCreate('caissier', 'api');
        $role = Role::findOrCreate('gérant', 'api');

        $this->asAdmin()
             ->putJson("/api/roles/{$role->id}", ['name' => 'caissier'])
             ->assertStatus(422);
    }

    #[Test]
    public function modification_peut_garder_le_meme_nom()
    {
        $role = Role::findOrCreate('gérant', 'api');

        $this->asAdmin()
             ->putJson("/api/roles/{$role->id}", ['name' => 'gérant'])
             ->assertStatus(200);
    }

    // =========================================================================
    // DELETE /api/roles/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_un_role()
    {
        $role = Role::findOrCreate('superviseur', 'api');

        $this->asAdmin()
             ->deleteJson("/api/roles/{$role->id}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    #[Test]
    public function impossible_de_supprimer_le_role_admin()
    {
        $adminRole = Role::findOrCreate('admin', 'api');

        $this->asAdmin()
             ->deleteJson("/api/roles/{$adminRole->id}")
             ->assertStatus(403)
             ->assertJsonPath('error', 'Cannot delete admin role');
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer_un_role()
    {
        $role = Role::findOrCreate('caissier', 'api');
        $this->deleteJson("/api/roles/{$role->id}")->assertStatus(401);
    }
}
