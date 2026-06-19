<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PointOfSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PermissionControllerTest extends TestCase
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
    // GET /api/permissions
    // =========================================================================

    #[Test]
    public function admin_peut_lister_les_permissions()
    {
        Permission::findOrCreate('view.sales', 'api');
        Permission::findOrCreate('create.sales', 'api');

        $response = $this->asAdmin()->getJson('/api/permissions');

        $response->assertStatus(200)
                 ->assertJsonIsArray();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('view.sales'));
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_lister_les_permissions()
    {
        $this->getJson('/api/permissions')->assertStatus(401);
    }

    // =========================================================================
    // POST /api/permissions
    // =========================================================================

    #[Test]
    public function admin_peut_creer_une_permission()
    {
        $response = $this->asAdmin()->postJson('/api/permissions', [
            'name' => 'export.reports',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'export.reports')
                 ->assertJsonPath('guard_name', 'api');

        $this->assertDatabaseHas('permissions', ['name' => 'export.reports', 'guard_name' => 'api']);
    }

    #[Test]
    public function creation_echoue_si_nom_manquant()
    {
        $this->asAdmin()
             ->postJson('/api/permissions', [])
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['name']]);
    }

    #[Test]
    public function creation_echoue_si_nom_deja_utilise()
    {
        Permission::findOrCreate('view.sales', 'api');

        $this->asAdmin()
             ->postJson('/api/permissions', ['name' => 'view.sales'])
             ->assertStatus(422);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_creer_une_permission()
    {
        $this->postJson('/api/permissions', ['name' => 'test.permission'])->assertStatus(401);
    }

    // =========================================================================
    // DELETE /api/permissions/{id}
    // =========================================================================

    #[Test]
    public function admin_peut_supprimer_une_permission()
    {
        $permission = Permission::findOrCreate('delete.reports', 'api');

        $this->asAdmin()
             ->deleteJson("/api/permissions/{$permission->id}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    #[Test]
    public function suppression_retourne_404_pour_permission_inexistante()
    {
        $this->asAdmin()
             ->deleteJson('/api/permissions/99999')
             ->assertStatus(404);
    }

    #[Test]
    public function non_authentifie_ne_peut_pas_supprimer_une_permission()
    {
        $permission = Permission::findOrCreate('view.sales', 'api');
        $this->deleteJson("/api/permissions/{$permission->id}")->assertStatus(401);
    }
}
